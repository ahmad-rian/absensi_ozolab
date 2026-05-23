<?php

use App\Models\NotificationLog;
use App\Models\Student;

test('guests are redirected from notifikasi page', function () {
    $this->get(route('admin.notifikasi'))->assertRedirect(route('login'));
});

test('authenticated users can visit the notifikasi page', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->get(route('admin.notifikasi'))
        ->assertOk();
});

test('notifikasi page returns expected props', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->get(route('admin.notifikasi'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/notifikasi/index')
            ->has('notifications')
            ->has('filters')
        );
});

test('notifikasi page filters by status', function () {
    $user = createAdminUser();
    $student = Student::factory()->create(['school_id' => $user->school_id]);

    NotificationLog::factory()->create(['status' => 'SENT', 'student_id' => $student->id, 'school_id' => $user->school_id]);
    NotificationLog::factory()->create(['status' => 'FAILED', 'student_id' => $student->id, 'school_id' => $user->school_id]);

    $this->actingAs($user)
        ->get(route('admin.notifikasi', ['status' => 'SENT']))
        ->assertInertia(fn ($page) => $page
            ->has('notifications.data', 1)
        );
});

test('notifikasi page filters by date range', function () {
    $user = createAdminUser();
    $student = Student::factory()->create(['school_id' => $user->school_id]);

    NotificationLog::factory()->create(['created_at' => '2026-01-10 10:00:00', 'student_id' => $student->id, 'school_id' => $user->school_id]);
    NotificationLog::factory()->create(['created_at' => '2026-01-20 10:00:00', 'student_id' => $student->id, 'school_id' => $user->school_id]);

    $this->actingAs($user)
        ->get(route('admin.notifikasi', ['date_from' => '2026-01-09', 'date_to' => '2026-01-11']))
        ->assertInertia(fn ($page) => $page
            ->has('notifications.data', 1)
        );
});
