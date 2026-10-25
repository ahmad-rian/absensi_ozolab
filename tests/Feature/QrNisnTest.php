<?php

use App\Enums\Religion;
use App\Models\AttendanceSchedule;
use App\Models\PrayerAttendance;
use App\Models\School;
use App\Models\Student;
use App\Services\Attendance\QrTokenGenerator;
use App\Services\Attendance\StudentLookup;
use App\Support\IsiQrSiswa;
use App\Support\SchoolTime;
use Carbon\Carbon;

/**
 * Sekolah yang meminta QR kartu berisi NISN polos (SMP 10 Purwokerto).
 *
 * NISN mudah ditebak, jadi jalurnya hanya terbuka untuk sekolah yang saklarnya
 * dinyalakan super admin. Sekolah lain tetap menolak NISN seperti sebelumnya.
 */
beforeEach(function () {
    $this->senin = SchoolTime::now()->startOfWeek(Carbon::MONDAY)->addWeek()->startOfDay();
});

function sekolahQrNisn(bool $aktif = true, array $settings = []): School
{
    $school = School::factory()->create([
        'settings' => array_merge([IsiQrSiswa::SETTING => $aktif], $settings),
    ]);

    foreach (range(1, 5) as $day) {
        AttendanceSchedule::factory()->create([
            'school_id' => $school->id,
            'classroom_id' => null,
            'day_of_week' => $day,
            'is_active' => true,
        ]);
    }

    return $school;
}

test('saklar mati: NISN polos tetap ditolak di gerbang', function () {
    $school = sekolahQrNisn(false);
    $siswa = Student::factory()->create(['school_id' => $school->id, 'nisn' => '0012345678']);
    $this->travelTo($this->senin->copy()->setTime(7, 0));

    $this->postJson(route('public.scanner.scan', $school->scanner_token), ['token' => '0012345678'])
        ->assertStatus(404);

    expect($siswa->attendances()->count())->toBe(0);
});

test('saklar nyala: NISN polos tercatat di gerbang', function () {
    $school = sekolahQrNisn();
    $siswa = Student::factory()->create(['school_id' => $school->id, 'nisn' => '0012345678']);
    $this->travelTo($this->senin->copy()->setTime(7, 0));

    $this->postJson(route('public.scanner.scan', $school->scanner_token), ['token' => '0012345678'])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($siswa->attendances()->count())->toBe(1);
});

test('saklar nyala: token lama tetap diterima', function () {
    $school = sekolahQrNisn();
    $siswa = Student::factory()->create(['school_id' => $school->id]);
    app(QrTokenGenerator::class)->generate($siswa);

    expect((new StudentLookup)->findByQrToken($siswa->fresh()->qr_token, $school->id)?->id)->toBe($siswa->id);
});

test('saklar nyala: NISN polos tercatat di scanner sholat', function () {
    $school = sekolahQrNisn(true, ['prayer_enabled' => true]);
    Student::factory()->create([
        'school_id' => $school->id,
        'nisn' => '0012345678',
        'religion' => Religion::Islam,
    ]);
    $this->travelTo($this->senin->copy()->setTime(11, 30));

    $this->postJson(route('public.prayer-scanner.scan', $school->scanner_token), ['token' => '0012345678'])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(PrayerAttendance::count())->toBe(1);
});

test('sampah di tepi bacaan dibuang, tapi isinya harus tepat 10 digit', function () {
    $school = sekolahQrNisn();
    $siswa = Student::factory()->create(['school_id' => $school->id, 'nisn' => '0012345678']);
    $lookup = new StudentLookup;

    expect($lookup->findByQrToken("0012345678T\n", $school->id)?->id)->toBe($siswa->id)
        ->and($lookup->findByQrToken('001234567', $school->id))->toBeNull()
        ->and($lookup->findByQrToken('00123456789', $school->id))->toBeNull()
        ->and($lookup->findByQrToken('00123-45678', $school->id))->toBeNull();
});

test('NISN ganda di satu sekolah ditolak', function () {
    $school = sekolahQrNisn();
    Student::factory()->count(2)->create(['school_id' => $school->id, 'nisn' => '0012345678']);

    expect((new StudentLookup)->findByQrToken('0012345678', $school->id))->toBeNull();
});

test('NISN milik sekolah lain dan siswa nonaktif ditolak', function () {
    $school = sekolahQrNisn();
    $lain = sekolahQrNisn();
    Student::factory()->create(['school_id' => $lain->id, 'nisn' => '0012345678']);
    Student::factory()->create(['school_id' => $school->id, 'nisn' => '0099999999', 'is_active' => false]);
    $lookup = new StudentLookup;

    expect($lookup->findByQrToken('0012345678', $school->id))->toBeNull()
        ->and($lookup->findByQrToken('0099999999', $school->id))->toBeNull();
});

test('isi QR kartu mengikuti saklar sekolah', function () {
    $nyala = sekolahQrNisn();
    $mati = sekolahQrNisn(false);

    $denganNisn = Student::factory()->create(['school_id' => $nyala->id, 'nisn' => '0012345678', 'qr_token' => 'tok-a']);
    $tanpaNisn = Student::factory()->create(['school_id' => $nyala->id, 'nisn' => null, 'qr_token' => 'tok-b']);
    $sekolahMati = Student::factory()->create(['school_id' => $mati->id, 'nisn' => '0087654321', 'qr_token' => 'tok-c']);

    expect(IsiQrSiswa::untuk($denganNisn))->toBe('0012345678')
        ->and(IsiQrSiswa::untuk($tanpaNisn))->toBe('tok-b')
        ->and(IsiQrSiswa::untuk($sekolahMati))->toBe('tok-c');
});

test('super admin menyalakan dan mematikan saklar tanpa menghapus setting lain', function () {
    $school = School::factory()->create(['name' => 'SMP 10', 'settings' => ['prayer_enabled' => true]]);

    $this->actingAs(createSuperAdminUser())
        ->put(route('admin.schools.update', $school), ['name' => 'SMP 10', 'qr_isi_nisn' => true])
        ->assertRedirect();

    expect($school->fresh()->getSetting(IsiQrSiswa::SETTING))->toBeTrue()
        ->and($school->fresh()->getSetting('prayer_enabled'))->toBeTrue();

    $this->actingAs(createSuperAdminUser())
        ->put(route('admin.schools.update', $school), ['name' => 'SMP 10', 'qr_isi_nisn' => false])
        ->assertRedirect();

    expect($school->fresh()->getSetting(IsiQrSiswa::SETTING))->toBeFalse();
});

test('admin sekolah tidak bisa menyalakan saklar', function () {
    $school = School::factory()->create(['name' => 'SMP 10']);

    $this->actingAs(createAdminUser(['school_id' => $school->id]))
        ->put(route('admin.schools.update', $school), ['name' => 'SMP 10', 'qr_isi_nisn' => true])
        ->assertForbidden();

    expect($school->fresh()->getSetting(IsiQrSiswa::SETTING))->toBeNull();
});
