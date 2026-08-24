<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Enums\NotificationChannel;
use App\Events\StudentCheckedIn;
use App\Jobs\SendAttendanceNotifications;
use App\Models\Attendance;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\Student;
use Illuminate\Support\Facades\Queue;

/**
 * Kebijakan WhatsApp dibalik: kabar per scan diserahkan ke Email dan Telegram,
 * WA hanya untuk terlambat / tidak hadir lewat sapuan. Yang diuji di sini adalah
 * jalur per scan — bahwa WA benar-benar keluar darinya, dan kanal lain tidak
 * ikut terbawa.
 *
 * @param  array<string, mixed>  $parentAttributes
 */
function scanAttendance(bool $alertOnly, AttendanceStatus $status, array $parentAttributes = []): Attendance
{
    $school = School::factory()->create([
        'settings' => [
            'whatsapp_enabled' => true,
            'notify_on_check_in' => true,
            'wa_alert_only' => $alertOnly,
        ],
    ]);

    $parent = ParentProfile::factory()->create([
        'school_id' => $school->id,
        'whatsapp_number' => '081200000001',
        'email' => 'ortu@example.test',
        ...$parentAttributes,
    ]);

    $student = Student::factory()->create([
        'school_id' => $school->id,
        'parent_profile_id' => $parent->id,
    ]);

    return Attendance::factory()->create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'type' => AttendanceType::CheckIn,
        'status' => $status,
    ]);
}

test('scan hadir tidak lagi mengantre WhatsApp saat kebijakan dibalik', function () {
    Queue::fake();

    event(new StudentCheckedIn(scanAttendance(alertOnly: true, status: AttendanceStatus::Hadir)));

    Queue::assertPushed(
        SendAttendanceNotifications::class,
        fn (SendAttendanceNotifications $job) => $job->skipChannels === [NotificationChannel::Whatsapp],
    );
});

/**
 * Yang terlambat pun tidak dikirimi WA di sini. Sapuan yang mengurusnya — kalau
 * jalur ini ikut mengirim, orang tua menerima dua pesan untuk satu
 * keterlambatan yang sama.
 */
test('scan terlambat juga tidak mengantre WhatsApp di jalur per scan', function () {
    Queue::fake();

    event(new StudentCheckedIn(scanAttendance(alertOnly: true, status: AttendanceStatus::Terlambat)));

    Queue::assertPushed(
        SendAttendanceNotifications::class,
        fn (SendAttendanceNotifications $job) => $job->skipChannels === [NotificationChannel::Whatsapp],
    );
});

test('sekolah yang belum membalik kebijakan tetap mengirim lewat semua kanal', function () {
    Queue::fake();

    event(new StudentCheckedIn(scanAttendance(alertOnly: false, status: AttendanceStatus::Hadir)));

    Queue::assertPushed(
        SendAttendanceNotifications::class,
        fn (SendAttendanceNotifications $job) => $job->skipChannels === [],
    );
});

/**
 * Orang tua yang hanya punya nomor WA tidak punya tujuan tersisa begitu WA
 * dilewati. Mengantrekan job untuknya hanya membebani antrean yang dipakai
 * bersama seluruh tenant untuk hasil nol.
 */
test('orang tua tanpa email dan telegram tidak menghasilkan job sama sekali', function () {
    Queue::fake();

    $attendance = scanAttendance(
        alertOnly: true,
        status: AttendanceStatus::Hadir,
        parentAttributes: ['email' => null, 'telegram_chat_id' => null],
    );

    event(new StudentCheckedIn($attendance));

    Queue::assertNotPushed(SendAttendanceNotifications::class);
});

test('orang tua yang sama tetap dapat job saat kebijakan belum dibalik', function () {
    Queue::fake();

    $attendance = scanAttendance(
        alertOnly: false,
        status: AttendanceStatus::Hadir,
        parentAttributes: ['email' => null, 'telegram_chat_id' => null],
    );

    event(new StudentCheckedIn($attendance));

    Queue::assertPushed(SendAttendanceNotifications::class);
});

test('sekolah baru langsung memakai kebijakan yang dibalik', function () {
    $admin = createSuperAdminUser();

    $this->actingAs($admin)
        ->post(route('admin.schools.store'), ['name' => 'Sekolah Alert Test'])
        ->assertRedirect();

    $school = School::where('name', 'Sekolah Alert Test')->firstOrFail();

    expect($school->getSetting('wa_alert_only'))->toBeTrue()
        ->and($school->getSetting('wa_daily_limit'))->toBe(50);
});
