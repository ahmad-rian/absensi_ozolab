<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;

test('guests are redirected from absensi page', function () {
    $this->get(route('admin.absensi'))->assertRedirect(route('login'));
});

test('authenticated users can visit the absensi page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.absensi'))
        ->assertOk();
});

test('absensi page returns expected props', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.absensi'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/absensi/index')
            ->has('attendances')
            ->has('classrooms')
            ->has('students')
            ->has('filters')
        );
});

test('absensi page filters by date', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();

    Attendance::factory()->create([
        'student_id' => $student->id,
        'attendance_date' => '2026-01-15',
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Hadir,
        'recorded_at' => '2026-01-15 08:00:00',
    ]);

    Attendance::factory()->create([
        'student_id' => $student->id,
        'attendance_date' => '2026-01-16',
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Hadir,
        'recorded_at' => '2026-01-16 08:00:00',
    ]);

    $this->actingAs($user)
        ->get(route('admin.absensi', ['date' => '2026-01-15']))
        ->assertInertia(fn ($page) => $page
            ->has('attendances.data', 1)
        );
});

test('absensi page filters by classroom', function () {
    $user = User::factory()->create();
    $classroom = Classroom::factory()->create();
    $student = Student::factory()->create(['classroom_id' => $classroom->id]);
    $otherStudent = Student::factory()->create();

    Attendance::factory()->create([
        'student_id' => $student->id,
        'attendance_date' => today(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Hadir,
        'recorded_at' => now(),
    ]);

    Attendance::factory()->create([
        'student_id' => $otherStudent->id,
        'attendance_date' => today(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Terlambat,
        'recorded_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.absensi', ['classroom_id' => $classroom->id]))
        ->assertInertia(fn ($page) => $page
            ->has('attendances.data', 1)
        );
});

test('absensi page filters by status', function () {
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
        'student_id' => Student::factory()->create()->id,
        'attendance_date' => today(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Terlambat,
        'recorded_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.absensi', ['status' => 'HADIR']))
        ->assertInertia(fn ($page) => $page
            ->has('attendances.data', 1)
        );
});

test('authenticated users can store manual attendance', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.absensi.store'), [
            'student_id' => $student->id,
            'type' => 'CHECK_IN',
            'status' => 'HADIR',
            'notes' => 'Input manual oleh admin',
            'recorded_at' => '2026-05-19 08:00',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('attendances', [
        'student_id' => $student->id,
        'type' => 'CHECK_IN',
        'status' => 'HADIR',
        'notes' => 'Input manual oleh admin',
        'recorded_by' => $user->id,
    ]);
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.absensi.store'), [])
        ->assertSessionHasErrors(['student_id', 'type', 'status']);
});

test('guests cannot store attendance', function () {
    $this->post(route('admin.absensi.store'), [
        'student_id' => 1,
        'type' => 'CHECK_IN',
        'status' => 'HADIR',
    ])->assertRedirect(route('login'));
});
