<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard returns stats props', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('dashboard')
        ->has('stats', fn ($stats) => $stats
            ->has('totalStudents')
            ->has('presentToday')
            ->has('lateToday')
            ->has('attendanceRate')
            ->has('totalStudentsDelta')
            ->has('presentTodayDelta')
        )
    );
});

test('dashboard stats reflect attendance data', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();

    Attendance::factory()->create([
        'student_id' => $student->id,
        'attendance_date' => today(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Hadir,
        'recorded_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('stats.presentToday', 1)
    );
});
