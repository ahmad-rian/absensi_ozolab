<?php

use App\Enums\AttendanceType;
use App\Events\StudentCheckedOut;
use App\Jobs\SendAttendanceNotifications;
use App\Models\Attendance;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\Student;
use Illuminate\Support\Facades\Queue;

function makeCheckoutAttendance(bool $notifyOnCheckout): Attendance
{
    $school = School::factory()->create([
        'settings' => [
            'whatsapp_enabled' => true,
            'notify_on_check_out' => $notifyOnCheckout,
        ],
    ]);

    $parent = ParentProfile::factory()->create([
        'school_id' => $school->id,
        'whatsapp_number' => '081200000001',
    ]);

    $student = Student::factory()->create([
        'school_id' => $school->id,
        'parent_profile_id' => $parent->id,
    ]);

    return Attendance::factory()->create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'type' => AttendanceType::CheckOut,
    ]);
}

test('checkout dispatches notification when notify_on_check_out is enabled', function () {
    Queue::fake();

    $attendance = makeCheckoutAttendance(notifyOnCheckout: true);

    event(new StudentCheckedOut($attendance));

    Queue::assertPushed(SendAttendanceNotifications::class);
});

test('checkout does not dispatch notification when notify_on_check_out is disabled', function () {
    Queue::fake();

    $attendance = makeCheckoutAttendance(notifyOnCheckout: false);

    event(new StudentCheckedOut($attendance));

    Queue::assertNotPushed(SendAttendanceNotifications::class);
});

test('new school defaults notify_on_check_out to true', function () {
    $admin = createSuperAdminUser();

    $this->actingAs($admin)
        ->post(route('admin.schools.store'), ['name' => 'Sekolah Notif Test'])
        ->assertRedirect();

    $school = School::where('name', 'Sekolah Notif Test')->firstOrFail();

    expect($school->getSetting('notify_on_check_out'))->toBeTrue();
});
