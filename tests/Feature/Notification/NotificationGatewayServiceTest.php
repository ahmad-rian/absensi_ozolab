<?php

use App\Enums\AttendanceType;
use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\SchoolChannelType;
use App\Mail\AttendanceNotificationMail;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\NotificationLog;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\SchoolNotificationChannel;
use App\Models\Student;
use App\Services\Notification\DefaultEmailGateway;
use App\Services\Notification\DefaultTelegramGateway;
use App\Services\Notification\DefaultWhatsAppGateway;
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

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

test('email gateway sends from the school sender address', function () {
    $school = School::factory()->create();
    activateChannel($school, SchoolChannelType::Email, ['sender_email' => 'absensi@sekolah.id', 'sender_name' => 'SD Contoh']);

    Mail::fake();

    expect((new DefaultEmailGateway)->sendTemplate('ortu@email.com', 'attendance', ['nama_siswa' => 'Budi', 'nama_sekolah' => 'SD Contoh'], $school->id))->toBeTrue();

    Mail::assertSent(AttendanceNotificationMail::class, fn ($mail) => $mail->hasTo('ortu@email.com')
        && $mail->hasFrom('absensi@sekolah.id'));
});

test('email gateway registers a per-school smtp mailer from channel settings', function () {
    $school = School::factory()->create();
    activateChannel($school, SchoolChannelType::Email, [
        'sender_email' => 'absensi@sekolah.id',
        'smtp_host' => 'smtp.sekolah.id',
        'smtp_port' => '465',
        'smtp_username' => 'akun@sekolah.id',
        'smtp_password' => 'rahasia',
        'smtp_encryption' => 'ssl',
    ]);

    Mail::fake();

    (new DefaultEmailGateway)->sendTemplate('ortu@email.com', 'attendance', ['nama_siswa' => 'Budi'], $school->id);

    expect(config('mail.mailers.school_smtp_'.$school->id.'.host'))->toBe('smtp.sekolah.id')
        ->and(config('mail.mailers.school_smtp_'.$school->id.'.port'))->toBe(465)
        ->and(config('mail.mailers.school_smtp_'.$school->id.'.encryption'))->toBe('ssl');
});

test('dispatcher sends email when channel active and parent has email', function () {
    $school = School::factory()->create();
    activateChannel($school, SchoolChannelType::Email, ['sender_email' => 'absensi@sekolah.id']);

    $parent = ParentProfile::factory()->create(['school_id' => $school->id, 'whatsapp_number' => '08123', 'telegram_chat_id' => null, 'email' => 'ortu@email.com']);
    $classroom = Classroom::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'parent_profile_id' => $parent->id, 'classroom_id' => $classroom->id]);
    $attendance = Attendance::factory()->create(['school_id' => $school->id, 'student_id' => $student->id]);

    Mail::fake();

    app(NotificationDispatcher::class)->dispatchAttendance($attendance);

    expect(NotificationLog::where('attendance_id', $attendance->id)->where('channel', NotificationChannel::Email)->where('status', NotificationStatus::Sent)->exists())->toBeTrue();
    Mail::assertSentCount(1);
});

test('email reflects check-out as Pulang', function () {
    $school = School::factory()->create();
    activateChannel($school, SchoolChannelType::Email, ['sender_email' => 'absensi@sekolah.id']);

    $parent = ParentProfile::factory()->create(['school_id' => $school->id, 'whatsapp_number' => '08123', 'telegram_chat_id' => null, 'email' => 'ortu@email.com']);
    $classroom = Classroom::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'parent_profile_id' => $parent->id, 'classroom_id' => $classroom->id]);
    $attendance = Attendance::factory()->create(['school_id' => $school->id, 'student_id' => $student->id, 'type' => AttendanceType::CheckOut]);

    Mail::fake();

    app(NotificationDispatcher::class)->dispatchAttendance($attendance);

    Mail::assertSent(AttendanceNotificationMail::class, fn ($mail) => ($mail->variables['jenis'] ?? null) === 'Pulang');
});

test('dispatcher skips email when parent has no email', function () {
    $school = School::factory()->create();
    activateChannel($school, SchoolChannelType::Email, ['sender_email' => 'absensi@sekolah.id']);

    $parent = ParentProfile::factory()->create(['school_id' => $school->id, 'whatsapp_number' => '08123', 'telegram_chat_id' => null, 'email' => null]);
    $classroom = Classroom::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'parent_profile_id' => $parent->id, 'classroom_id' => $classroom->id]);
    $attendance = Attendance::factory()->create(['school_id' => $school->id, 'student_id' => $student->id]);

    Mail::fake();

    app(NotificationDispatcher::class)->dispatchAttendance($attendance);

    expect(NotificationLog::where('attendance_id', $attendance->id)->where('channel', NotificationChannel::Email)->exists())->toBeFalse();
    Mail::assertNothingSent();
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
