<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Enums\Religion;
use App\Models\Attendance;
use App\Models\PrayerAttendance;
use App\Models\Student;
use App\Services\Student\StudentStatsBuilder;
use App\Support\SchoolTime;

beforeEach(function () {
    $this->admin = createAdminUser();
    $this->admin->school->setSetting('prayer_enabled', true);

    $this->student = Student::factory()->create([
        'school_id' => $this->admin->school_id,
        'nis' => '20250099',
        'full_name' => 'Siti Rahmawati',
        'religion' => Religion::Islam,
    ]);

    $this->day = SchoolTime::now()->startOfMonth()->addDays(2);
    $this->start = SchoolTime::now()->startOfMonth()->toDateString();
    $this->end = SchoolTime::now()->endOfMonth()->toDateString();

    Attendance::factory()->create([
        'school_id' => $this->admin->school_id,
        'student_id' => $this->student->id,
        'attendance_date' => $this->day->toDateString(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Terlambat,
        'recorded_at' => $this->day->copy()->setTime(8, 30),
    ]);

    PrayerAttendance::factory()->create([
        'school_id' => $this->admin->school_id,
        'student_id' => $this->student->id,
        'prayer_date' => $this->day->toDateString(),
        'recorded_at' => $this->day->copy()->setTime(11, 30),
    ]);
});

function reportUrl(string $kind, string $format): string
{
    return route("admin.siswa.laporan.{$kind}.{$format}", test()->student).
        '?start_date='.test()->start.'&end_date='.test()->end;
}

test('the attendance csv contains the identity, summary and rows', function () {
    $response = $this->actingAs($this->admin)->get(reportUrl('absensi', 'csv'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->headers->get('content-disposition'))->toContain('absensi-20250099-');

    $body = $response->streamedContent();

    expect($body)->toStartWith("\xEF\xBB\xBF")
        ->and($body)->toContain('SITI RAHMAWATI')
        ->and($body)->toContain('Terlambat')
        ->and($body)->toContain('% Kehadiran');
});

test('the attendance pdf downloads', function () {
    $response = $this->actingAs($this->admin)->get(reportUrl('absensi', 'pdf'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('absensi-20250099-');
});

test('the prayer csv only reports prayer rows', function () {
    $response = $this->actingAs($this->admin)->get(reportUrl('sholat', 'csv'));

    $response->assertOk();
    $body = $response->streamedContent();

    expect($body)->toContain('Ikut Sholat')
        ->and($body)->toContain('SITI RAHMAWATI')
        // Kolom khas laporan absensi sekolah tidak boleh bocor ke laporan sholat.
        ->and($body)->not->toContain('Terlambat');
});

test('the prayer pdf downloads', function () {
    $response = $this->actingAs($this->admin)->get(reportUrl('sholat', 'pdf'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('sholat-20250099-');
});

test('reports for another school student are not reachable', function () {
    $other = Student::factory()->create();

    foreach (['absensi', 'sholat'] as $kind) {
        foreach (['csv', 'pdf'] as $format) {
            $this->actingAs($this->admin)
                ->get(route("admin.siswa.laporan.{$kind}.{$format}", $other))
                ->assertNotFound();
        }
    }
});

test('reports require the siswa permission', function () {
    $guru = createAdminUser(['school_id' => $this->admin->school_id]);
    $guru->syncRoles(['GURU']);
    $guru->roles()->first()->revokePermissionTo('siswa.access');

    $this->actingAs($guru)
        ->get(reportUrl('absensi', 'csv'))
        ->assertForbidden();
});

test('guests are redirected away from the reports', function () {
    $this->get(reportUrl('absensi', 'csv'))->assertRedirect(route('login'));
});

// ---------------------------------------------------------------- Per jenis

test('the prayer csv can be narrowed to dhuha only', function () {
    $this->admin->school->setSetting('prayer_dhuha_enabled', true);

    PrayerAttendance::factory()->dhuha()->create([
        'school_id' => $this->admin->school_id,
        'student_id' => $this->student->id,
        'prayer_date' => $this->day->toDateString(),
    ]);

    $body = $this->actingAs($this->admin)
        ->get(reportUrl('sholat', 'csv').'&jenis=dhuha')
        ->streamedContent();

    expect($body)->toContain('Sholat Dhuha')
        ->and($body)->not->toContain('Sholat Dzuhur');
});

test('the prayer csv without a jenis reports every active type', function () {
    $this->admin->school->setSetting('prayer_dhuha_enabled', true);

    PrayerAttendance::factory()->dhuha()->create([
        'school_id' => $this->admin->school_id,
        'student_id' => $this->student->id,
        'prayer_date' => $this->day->toDateString(),
    ]);

    $body = $this->actingAs($this->admin)
        ->get(reportUrl('sholat', 'csv'))
        ->streamedContent();

    expect($body)->toContain('Sholat Dhuha')
        ->and($body)->toContain('Sholat Dzuhur');
});

test('an unknown jenis falls back to every type instead of 404', function () {
    // Tautan lama atau salah ketik tetap harus menghasilkan laporan.
    $this->actingAs($this->admin)
        ->get(reportUrl('sholat', 'csv').'&jenis=ngawur')
        ->assertOk();
});

test('the dhuha report gets its own filename', function () {
    $this->admin->school->setSetting('prayer_dhuha_enabled', true);

    $this->actingAs($this->admin)
        ->get(reportUrl('sholat', 'pdf').'&jenis=dhuha')
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=sholat-dhuha-20250099-'.$this->start.'-'.$this->end.'.pdf');
});

// ---------------------------------------------------------------- Regresi audit

test('B-3: asking for a disabled type reports zero, not the other type', function () {
    // Dhuha mati (default). Dulu filter jenisnya dilewati sepenuhnya, sehingga
    // laporan berjudul Dhuha menampilkan angka Dzuhur dengan tabel kosong.
    $body = $this->actingAs($this->admin)
        ->get(reportUrl('sholat', 'csv').'&jenis=dhuha')
        ->streamedContent();

    expect($body)->toContain('"Ikut Sholat",0')
        ->and($body)->not->toContain('Sholat Dzuhur');
});

test('B-3: the rate can never exceed 100 percent', function () {
    $this->admin->school->setSetting('prayer_dhuha_enabled', true);

    PrayerAttendance::factory()->dhuha()->create([
        'school_id' => $this->admin->school_id,
        'student_id' => $this->student->id,
        'prayer_date' => $this->day->toDateString(),
    ]);

    $data = app(StudentStatsBuilder::class)
        ->prayerFor($this->student, $this->start, $this->end);

    expect($data['summary']['rate'])->toBeLessThanOrEqual(100.0);
});

test('B-4: the prayer history is ordered newest first across months', function () {
    // sortByDesc atas string 'd M Y' menaruh "30 Jun" di atas "05 Jul".
    $school = $this->admin->school;
    $school->setSetting('prayer_start', '00:00');
    $school->setSetting('prayer_end', '23:59');

    $june = SchoolTime::now()->startOfMonth()->subMonth()->addDays(27);
    $july = SchoolTime::now()->startOfMonth()->addDays(4);

    foreach ([$june, $july] as $day) {
        Attendance::factory()->create([
            'school_id' => $school->id,
            'student_id' => $this->student->id,
            'attendance_date' => $day->toDateString(),
            'type' => AttendanceType::CheckIn,
        ]);

        PrayerAttendance::factory()->create([
            'school_id' => $school->id,
            'student_id' => $this->student->id,
            'prayer_date' => $day->toDateString(),
            'recorded_at' => $day->copy()->setTime(11, 30),
        ]);
    }

    $data = app(StudentStatsBuilder::class)
        ->prayerFor($this->student, $june->toDateString(), $july->toDateString());

    $dates = collect($data['recent'])->pluck('date')->all();

    expect($dates[0])->toBe($july->format('d M Y'));
});

test('B-12: an array jenis parameter does not blow up', function () {
    $this->actingAs($this->admin)
        ->get(reportUrl('sholat', 'csv').'&jenis[]=dhuha')
        ->assertOk();
});
