<?php

use App\Enums\NotificationChannel;
use App\Enums\SchoolChannelType;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\NotificationLog;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\SchoolNotificationChannel;
use App\Models\Student;
use App\Services\Notification\DefaultTelegramGateway;
use App\Services\Notification\DefaultWhatsAppGateway;
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Support\Facades\Http;

function activateChannel(School $school, SchoolChannelType $type, array $settings = []): SchoolNotificationChannel
{
    return SchoolNotificationChannel::create([
        'school_id' => $school->id,
        'channel' => $type,
        'is_active' => true,
        'settings' => $settings === [] ? null : $settings,
    ]);
}

test('whatsapp gateway prefers active fonnte over ozolab', function () {
    $school = School::factory()->create();
    activateChannel($school, SchoolChannelType::FonnteWa, ['fonnte_token' => 'tok-1234567890']);

    Http::fake(['api.fonnte.com/*' => Http::response(['status' => true])]);

    expect((new DefaultWhatsAppGateway)->sendText('08123456789', 'hi', $school->id))->toBeTrue();
    Http::assertSent(fn ($r) => str_contains($r->url(), 'fonnte.com'));
});

test('whatsapp gateway falls back to ozolab when only ozolab active', function () {
    $school = School::factory()->create();
    activateChannel($school, SchoolChannelType::OzolabWa);

    config(['whatsapp.api_key' => 'key', 'whatsapp.sender' => '628', 'whatsapp.base_url' => 'https://wa.ozolab.id']);
    Http::fake(['wa.ozolab.id/*' => Http::response(['status' => true])]);

    expect((new DefaultWhatsAppGateway)->sendText('08123456789', 'hi', $school->id))->toBeTrue();
    Http::assertSent(fn ($r) => str_contains($r->url(), 'ozolab'));
});

test('whatsapp gateway skips when no active channel', function () {
    $school = School::factory()->create();
    Http::fake();

    expect((new DefaultWhatsAppGateway)->sendText('08123456789', 'hi', $school->id))->toBeFalse();
    Http::assertNothingSent();
});

test('telegram gateway posts to bot sendMessage endpoint', function () {
    $school = School::factory()->create();
    activateChannel($school, SchoolChannelType::Telegram, ['bot_token' => '12345:ABCDEF_token_long']);

    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    expect((new DefaultTelegramGateway)->sendText('999', 'hi', $school->id))->toBeTrue();
    Http::assertSent(fn ($r) => str_contains($r->url(), 'bot12345:ABCDEF_token_long/sendMessage'));
});

test('telegram gateway cleans a double encoded template', function () {
    $school = School::factory()->create();
    $school->setSetting('whatsapp_template_attendance', json_encode('Halo Bapak/Ibu Wali, ananda {nama_siswa}.'));
    $school->save();
    activateChannel($school, SchoolChannelType::Telegram, ['bot_token' => '12345:ABCDEF_token_long']);

    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    (new DefaultTelegramGateway)->sendTemplate('999', 'attendance', ['nama_siswa' => 'Budi'], $school->id);

    Http::assertSent(fn ($r) => $r['text'] === 'Halo Bapak/Ibu Wali, ananda Budi.');
});

test('dispatcher logs one notification per active channel', function () {
    $school = School::factory()->create();
    activateChannel($school, SchoolChannelType::OzolabWa);
    activateChannel($school, SchoolChannelType::Telegram, ['bot_token' => '12345:ABCDEF_token_long']);
    config(['whatsapp.api_key' => 'key', 'whatsapp.sender' => '628']);

    $parent = ParentProfile::factory()->create(['school_id' => $school->id, 'whatsapp_number' => '08123', 'telegram_chat_id' => '999']);
    $classroom = Classroom::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'parent_profile_id' => $parent->id, 'classroom_id' => $classroom->id]);
    $attendance = Attendance::factory()->create(['school_id' => $school->id, 'student_id' => $student->id]);

    Http::fake([
        'wa.ozolab.id/*' => Http::response(['status' => true]),
        'api.telegram.org/*' => Http::response(['ok' => true]),
    ]);

    app(NotificationDispatcher::class)->dispatchAttendance($attendance);

    expect(NotificationLog::where('attendance_id', $attendance->id)->count())->toBe(2)
        ->and(NotificationLog::where('attendance_id', $attendance->id)->where('channel', NotificationChannel::Telegram)->exists())->toBeTrue();
});

test('dispatcher skips telegram when parent has no chat id', function () {
    $school = School::factory()->create();
    activateChannel($school, SchoolChannelType::OzolabWa);
    activateChannel($school, SchoolChannelType::Telegram, ['bot_token' => '12345:ABCDEF_token_long']);
    config(['whatsapp.api_key' => 'key', 'whatsapp.sender' => '628']);

    $parent = ParentProfile::factory()->create(['school_id' => $school->id, 'whatsapp_number' => '08123', 'telegram_chat_id' => null]);
    $classroom = Classroom::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'parent_profile_id' => $parent->id, 'classroom_id' => $classroom->id]);
    $attendance = Attendance::factory()->create(['school_id' => $school->id, 'student_id' => $student->id]);

    Http::fake(['wa.ozolab.id/*' => Http::response(['status' => true])]);

    app(NotificationDispatcher::class)->dispatchAttendance($attendance);

    expect(NotificationLog::where('attendance_id', $attendance->id)->count())->toBe(1)
        ->and(NotificationLog::where('attendance_id', $attendance->id)->where('channel', NotificationChannel::Telegram)->exists())->toBeFalse();
});

test('dispatcher is idempotent for already sent channel', function () {
    $school = School::factory()->create();
    activateChannel($school, SchoolChannelType::OzolabWa);
    config(['whatsapp.api_key' => 'key', 'whatsapp.sender' => '628']);

    $parent = ParentProfile::factory()->create(['school_id' => $school->id, 'whatsapp_number' => '08123', 'telegram_chat_id' => null]);
    $classroom = Classroom::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'parent_profile_id' => $parent->id, 'classroom_id' => $classroom->id]);
    $attendance = Attendance::factory()->create(['school_id' => $school->id, 'student_id' => $student->id]);

    Http::fake(['wa.ozolab.id/*' => Http::response(['status' => true])]);

    $dispatcher = app(NotificationDispatcher::class);
    $dispatcher->dispatchAttendance($attendance, 1);
    $dispatcher->dispatchAttendance($attendance, 2);

    expect(NotificationLog::where('attendance_id', $attendance->id)->count())->toBe(1);
    Http::assertSentCount(1);
});
