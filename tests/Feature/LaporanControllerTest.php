<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;

test('guests are redirected from laporan page', function () {
    $this->get(route('admin.laporan'))->assertRedirect(route('login'));
});

test('authenticated users can visit laporan page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.laporan'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/laporan/index')
        ->has('reportData')
        ->has('summary')
        ->has('classrooms')
        ->has('filters')
    );
});

test('laporan returns attendance summary data', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();

    Attendance::factory()->create([
        'student_id' => $student->id,
        'attendance_date' => today(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Hadir,
        'recorded_at' => now(),
    ]);

    Attendance::factory()->create([
        'student_id' => $student->id,
        'attendance_date' => today()->subDay(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Terlambat,
        'recorded_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('admin.laporan', [
        'start_date' => today()->startOfMonth()->toDateString(),
        'end_date' => today()->endOfMonth()->toDateString(),
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('reportData', 1)
        ->where('summary.total_hadir', 1)
        ->where('summary.total_terlambat', 1)
    );
});

test('guests are redirected from laporan export', function () {
    $this->get(route('admin.laporan.export'))->assertRedirect(route('login'));
});

test('authenticated users can export laporan as csv', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();

    Attendance::factory()->create([
        'student_id' => $student->id,
        'attendance_date' => today(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Hadir,
        'recorded_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('admin.laporan.export', [
        'start_date' => today()->startOfMonth()->toDateString(),
        'end_date' => today()->endOfMonth()->toDateString(),
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $response->assertDownload();

    $content = $response->streamedContent();
    expect($content)->toContain('NIS')
        ->toContain('Nama Siswa')
        ->toContain('% Kehadiran')
        ->toContain($student->nis)
        ->toContain($student->full_name);
});

test('laporan export filters by classroom', function () {
    $user = User::factory()->create();
    $classroom1 = Classroom::factory()->create();
    $classroom2 = Classroom::factory()->create();
    $student1 = Student::factory()->create(['classroom_id' => $classroom1->id]);
    $student2 = Student::factory()->create(['classroom_id' => $classroom2->id]);

    Attendance::factory()->create([
        'student_id' => $student1->id,
        'attendance_date' => today(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Hadir,
        'recorded_at' => now(),
    ]);

    Attendance::factory()->create([
        'student_id' => $student2->id,
        'attendance_date' => today(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Hadir,
        'recorded_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('admin.laporan.export', [
        'start_date' => today()->startOfMonth()->toDateString(),
        'end_date' => today()->endOfMonth()->toDateString(),
        'classroom_id' => $classroom1->id,
    ]));

    $response->assertOk();

    $content = $response->streamedContent();
    expect($content)->toContain($student1->full_name)
        ->not->toContain($student2->full_name);
});

test('laporan filters by classroom', function () {
    $user = User::factory()->create();
    $classroom1 = Classroom::factory()->create();
    $classroom2 = Classroom::factory()->create();
    $student1 = Student::factory()->create(['classroom_id' => $classroom1->id]);
    $student2 = Student::factory()->create(['classroom_id' => $classroom2->id]);

    Attendance::factory()->create([
        'student_id' => $student1->id,
        'attendance_date' => today(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Hadir,
        'recorded_at' => now(),
    ]);

    Attendance::factory()->create([
        'student_id' => $student2->id,
        'attendance_date' => today(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Hadir,
        'recorded_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('admin.laporan', [
        'start_date' => today()->startOfMonth()->toDateString(),
        'end_date' => today()->endOfMonth()->toDateString(),
        'classroom_id' => $classroom1->id,
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('reportData', 1)
    );
});
