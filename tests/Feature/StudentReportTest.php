<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Enums\Religion;
use App\Models\Attendance;
use App\Models\PrayerAttendance;
use App\Models\Student;
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
        ->and($body)->toContain('Siti Rahmawati')
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
        ->and($body)->toContain('Siti Rahmawati')
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
