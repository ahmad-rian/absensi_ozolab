<?php

use App\Models\NotificationLog;
use App\Models\School;
use App\Models\Student;

beforeEach(function () {
    $this->admin = createAdminUser();
    $this->student = Student::factory()->create(['school_id' => $this->admin->school_id]);
});

function makeLog(string $schoolId, ?Student $student = null, ?string $readAt = null): NotificationLog
{
    return NotificationLog::factory()->create([
        'school_id' => $schoolId,
        'student_id' => ($student ?? Student::factory()->create(['school_id' => $schoolId]))->id,
        'read_at' => $readAt,
    ]);
}

test('the inbox reports how many logs are unread', function () {
    makeLog($this->admin->school_id, $this->student);
    makeLog($this->admin->school_id, $this->student, now()->toDateTimeString());

    $this->actingAs($this->admin)
        ->get(route('admin.notifikasi'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/notifikasi/index')
            ->where('unreadCount', 1)
            ->has('notifications.data', 2)
        );
});

test('the unread count is shared with every page for the sidebar badge', function () {
    makeLog($this->admin->school_id, $this->student);
    makeLog($this->admin->school_id, $this->student);

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('notifications.unread', 2));
});

test('marking all read empties the badge', function () {
    makeLog($this->admin->school_id, $this->student);
    makeLog($this->admin->school_id, $this->student);

    $this->actingAs($this->admin)
        ->post(route('admin.notifikasi.read-all'))
        ->assertRedirect();

    expect(NotificationLog::whereNull('read_at')->count())->toBe(0);
});

test('marking all read never touches another school', function () {
    $otherSchool = School::factory()->create();
    $foreign = makeLog($otherSchool->id);
    makeLog($this->admin->school_id, $this->student);

    $this->actingAs($this->admin)->post(route('admin.notifikasi.read-all'));

    expect($foreign->fresh()->read_at)->toBeNull();
});

test('a single log can be marked read', function () {
    $log = makeLog($this->admin->school_id, $this->student);

    $this->actingAs($this->admin)
        ->post(route('admin.notifikasi.read', $log))
        ->assertRedirect();

    expect($log->fresh()->read_at)->not->toBeNull();
});

test('a log can be deleted', function () {
    $log = makeLog($this->admin->school_id, $this->student);

    $this->actingAs($this->admin)
        ->delete(route('admin.notifikasi.destroy', $log))
        ->assertRedirect();

    expect(NotificationLog::find($log->id))->toBeNull();
});

test('only read logs are cleared by the bulk delete', function () {
    $unread = makeLog($this->admin->school_id, $this->student);
    $read = makeLog($this->admin->school_id, $this->student, now()->toDateTimeString());

    $this->actingAs($this->admin)
        ->delete(route('admin.notifikasi.destroy-read'))
        ->assertRedirect();

    expect(NotificationLog::find($read->id))->toBeNull()
        ->and(NotificationLog::find($unread->id))->not->toBeNull();
});

test('another school log cannot be read or deleted', function () {
    $otherSchool = School::factory()->create();
    $foreign = makeLog($otherSchool->id);

    $this->actingAs($this->admin)
        ->post(route('admin.notifikasi.read', $foreign))
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->delete(route('admin.notifikasi.destroy', $foreign))
        ->assertNotFound();

    expect(NotificationLog::withoutGlobalScope('school')->find($foreign->id))->not->toBeNull();
});

test('the unread filter narrows the list', function () {
    makeLog($this->admin->school_id, $this->student);
    makeLog($this->admin->school_id, $this->student, now()->toDateTimeString());

    $this->actingAs($this->admin)
        ->get(route('admin.notifikasi', ['unread' => '1']))
        ->assertInertia(fn ($page) => $page->has('notifications.data', 1));
});

test('guests cannot touch the inbox', function () {
    $log = makeLog($this->admin->school_id, $this->student);

    $this->post(route('admin.notifikasi.read-all'))->assertRedirect(route('login'));
    $this->delete(route('admin.notifikasi.destroy', $log))->assertRedirect(route('login'));
});
