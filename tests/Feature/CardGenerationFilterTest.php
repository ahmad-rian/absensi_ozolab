<?php

use App\Models\CardGenerationLog;
use App\Models\SchoolCardLayout;
use App\Models\Student;
use App\Support\SchoolTime;
use Carbon\CarbonImmutable;

/*
 | Riwayat generate kartu: jam yang benar, dan rentang yang dipotong di tengah
 | malam WIB.
 |
 | Dua hal yang dijaga di sini pernah salah bersamaan dan terlihat sama dari luar
 | — "waktunya aneh". Yang pertama adalah tampilan: `created_at` diisi Eloquent
 | pada `config('app.timezone')` yang di aplikasi ini UTC, jadi memformatnya
 | langsung membuat kartu pukul 08.41 WIB muncul sebagai "01:41". Yang kedua
 | adalah query: `whereDate()` membandingkan tanggal UTC yang tersimpan, sehingga
 | generate sore hari terhitung sebagai besok.
 */

beforeEach(function () {
    $this->admin = createAdminUser();
    $this->schoolId = $this->admin->school_id;
    $this->student = Student::factory()->create(['school_id' => $this->schoolId]);

    $this->layout = SchoolCardLayout::create([
        'school_id' => $this->schoolId,
        'name' => 'Kartu OSIS',
        'type' => 'osis',
        'layout_config' => [],
    ]);
});

/** Satu log yang dibuat pada jam dinding WIB tertentu. */
function logAtWib(string $schoolId, Student $student, ?string $layoutId, string $wib, array $attributes = []): CardGenerationLog
{
    $log = CardGenerationLog::create([
        'school_id' => $schoolId,
        'student_id' => $student->id,
        'school_card_layout_id' => $layoutId,
        'type' => 'card',
        'status' => 'completed',
        ...$attributes,
    ]);

    // Seperti Eloquent menuliskannya: instan yang sama, disimpan dalam UTC.
    $log->forceFill([
        'created_at' => CarbonImmutable::parse($wib, SchoolTime::timezone())->utc(),
    ])->saveQuietly();

    return $log;
}

function cardLogs($response): array
{
    return $response->viewData('page')['props']['logs']['data'];
}

test('a card generated at 08:41 WIB is shown as 08:41, not 01:41', function () {
    logAtWib($this->schoolId, $this->student, $this->layout->id, '2026-08-21 08:41:00');

    $response = $this->actingAs($this->admin)->get('/admin/card-generation');

    $response->assertOk();
    expect(cardLogs($response)[0]['created_at'])->toBe('21 Aug 2026 08:41');
});

test('today stops at midnight WIB, not at midnight UTC', function () {
    /*
     | 23:30 WIB hari ini tersimpan sebagai 16:30 UTC hari yang sama, sedangkan
     | 00:30 WIB besok tersimpan sebagai 17:30 UTC HARI INI. Filter yang bekerja
     | pada tanggal UTC akan menukar keduanya: yang seharusnya masuk terbuang,
     | yang seharusnya besok ikut terhitung.
     */
    $today = SchoolTime::now()->startOfDay();

    logAtWib($this->schoolId, $this->student, $this->layout->id, $today->copy()->setTime(23, 30)->toDateTimeString());
    logAtWib($this->schoolId, $this->student, $this->layout->id, $today->copy()->addDay()->setTime(0, 30)->toDateTimeString());
    logAtWib($this->schoolId, $this->student, $this->layout->id, $today->copy()->subDay()->setTime(10, 0)->toDateTimeString());

    $response = $this->actingAs($this->admin)->get('/admin/card-generation?range=today');

    $response->assertOk();
    $logs = cardLogs($response);

    expect($logs)->toHaveCount(1)
        ->and($logs[0]['created_at'])->toBe($today->copy()->setTime(23, 30)->format('d M Y H:i'));
});

test('a custom range covers both of its end days in full', function () {
    // Batas yang dibulatkan ke tengah malam UTC akan memotong jam sore di hari
    // terakhir — persis rentang yang paling sering dipilih operator.
    logAtWib($this->schoolId, $this->student, $this->layout->id, '2026-08-10 00:05:00');
    logAtWib($this->schoolId, $this->student, $this->layout->id, '2026-08-12 22:50:00');
    logAtWib($this->schoolId, $this->student, $this->layout->id, '2026-08-13 09:00:00');

    $response = $this->actingAs($this->admin)
        ->get('/admin/card-generation?range=custom&start_date=2026-08-10&end_date=2026-08-12');

    $response->assertOk();
    expect(cardLogs($response))->toHaveCount(2);
});

test('without a range every entry is listed', function () {
    logAtWib($this->schoolId, $this->student, $this->layout->id, '2024-01-01 09:00:00');
    logAtWib($this->schoolId, $this->student, $this->layout->id, SchoolTime::now()->toDateTimeString());

    $response = $this->actingAs($this->admin)->get('/admin/card-generation');

    expect(cardLogs($response))->toHaveCount(2);
});

test('status, type and student search each narrow the list', function () {
    logAtWib($this->schoolId, $this->student, $this->layout->id, '2026-08-20 09:00:00', ['status' => 'failed']);
    logAtWib($this->schoolId, $this->student, null, '2026-08-20 10:00:00', ['type' => 'photo_sheet']);

    $other = Student::factory()->create(['school_id' => $this->schoolId, 'full_name' => 'BUDI SANTOSA']);
    logAtWib($this->schoolId, $other, $this->layout->id, '2026-08-20 11:00:00');

    $this->actingAs($this->admin);

    expect(cardLogs($this->get('/admin/card-generation?status=failed')))->toHaveCount(1)
        ->and(cardLogs($this->get('/admin/card-generation?type=photo_sheet')))->toHaveCount(1)
        ->and(cardLogs($this->get('/admin/card-generation?search=BUDI')))->toHaveCount(1);
});

test('an unknown range falls back to everything instead of an empty page', function () {
    // Riwayat kosong tanpa alasan yang terlihat adalah laporan bug berikutnya.
    logAtWib($this->schoolId, $this->student, $this->layout->id, '2026-08-20 09:00:00');

    $response = $this->actingAs($this->admin)->get('/admin/card-generation?range=kemarin-dulu');

    $response->assertOk();
    expect(cardLogs($response))->toHaveCount(1);
});
