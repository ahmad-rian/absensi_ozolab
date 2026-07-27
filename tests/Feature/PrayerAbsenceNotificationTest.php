<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Enums\NotificationChannel;
use App\Enums\PrayerType;
use App\Enums\Religion;
use App\Enums\SchoolChannelType;
use App\Jobs\SendPrayerAbsenceNotification;
use App\Mail\PrayerAbsenceNotificationMail;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\NotificationLog;
use App\Models\ParentProfile;
use App\Models\PrayerAbsenceAlert;
use App\Models\PrayerAttendance;
use App\Models\School;
use App\Models\SchoolNotificationChannel;
use App\Models\Student;
use App\Services\Notification\NotificationDispatcher;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // Dihitung sebelum travel apa pun supaya rentangnya tidak bergeser.
    $this->monday = SchoolTime::now()->startOfWeek(Carbon::MONDAY)->subWeek()->startOfDay();

    $this->school = School::factory()->create([
        'is_active' => true,
        'settings' => [
            'prayer_enabled' => true,
            'feature_notif_alpa_sholat' => true,
            'prayer_absence_threshold' => 3,
            'prayer_absence_require_present' => true,
        ],
    ]);

    foreach (range(1, 5) as $day) {
        AttendanceSchedule::factory()->create([
            'school_id' => $this->school->id,
            'classroom_id' => null,
            'day_of_week' => $day,
            'is_active' => true,
        ]);
    }

    $this->parent = ParentProfile::factory()->create([
        'school_id' => $this->school->id,
        'email' => 'ortu@example.test',
    ]);

    $this->student = Student::factory()->create([
        'school_id' => $this->school->id,
        'parent_profile_id' => $this->parent->id,
        'religion' => Religion::Islam,
        'is_active' => true,
    ]);
});

/** Tandai siswa hadir di sekolah pada offset hari kerja ke-n dari Senin. */
function markPresent(Student $student, Carbon $day): void
{
    Attendance::factory()->create([
        'school_id' => $student->school_id,
        'student_id' => $student->id,
        'attendance_date' => $day->toDateString(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Hadir,
        'recorded_at' => $day->copy()->setTime(7, 0),
    ]);
}

function markPrayed(Student $student, Carbon $day, PrayerType $type = PrayerType::Dzuhur): void
{
    PrayerAttendance::factory()->create([
        'school_id' => $student->school_id,
        'student_id' => $student->id,
        'prayer_date' => $day->toDateString(),
        'prayer_type' => $type,
    ]);
}

function runAbsenceCommand(Carbon $upTo, array $options = []): void
{
    test()->artisan('prayer:notify-absence', array_merge(['--date' => $upTo->toDateString()], $options))
        ->assertSuccessful();
}

// ---------------------------------------------------------------- Deteksi

test('three consecutive school days without a prayer raise one alert', function () {
    Queue::fake();

    foreach ([0, 1, 2] as $offset) {
        markPresent($this->student, $this->monday->copy()->addDays($offset));
    }

    runAbsenceCommand($this->monday->copy()->addDays(2));

    $alert = PrayerAbsenceAlert::withoutGlobalScope('school')->first();

    expect($alert)->not->toBeNull()
        ->and($alert->streak_length)->toBe(3)
        ->and($alert->prayer_type)->toBe(PrayerType::Dzuhur)
        ->and($alert->notified_at)->not->toBeNull();

    Queue::assertPushed(SendPrayerAbsenceNotification::class, 1);
});

test('the streak resets once the student prays again', function () {
    Queue::fake();

    foreach ([0, 1, 2, 3, 4] as $offset) {
        markPresent($this->student, $this->monday->copy()->addDays($offset));
    }

    // Alpa, alpa, sholat, alpa, alpa — tidak pernah 3 beruntun.
    markPrayed($this->student, $this->monday->copy()->addDays(2));

    runAbsenceCommand($this->monday->copy()->addDays(4));

    expect(PrayerAbsenceAlert::withoutGlobalScope('school')->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('a day the school did not operate is skipped, not counted', function () {
    Queue::fake();

    // Tidak ada satu pun baris absensi di hari Selasa → dianggap libur.
    foreach ([0, 2, 3] as $offset) {
        markPresent($this->student, $this->monday->copy()->addDays($offset));
    }

    runAbsenceCommand($this->monday->copy()->addDays(3));

    // Tiga hari sekolah efektif yang terlewat tetap 3 — hari libur tidak
    // memutus rentetan dan tidak ikut dihitung.
    $alert = PrayerAbsenceAlert::withoutGlobalScope('school')->first();

    expect($alert->streak_length)->toBe(3);
});

test('a day the student was absent from school is neutral', function () {
    Queue::fake();

    // Siswa hadir hanya 2 hari; hari sakit di tengah tidak menambah rentetan.
    markPresent($this->student, $this->monday);
    Attendance::factory()->create([
        'school_id' => $this->school->id,
        'student_id' => Student::factory()->create(['school_id' => $this->school->id])->id,
        'attendance_date' => $this->monday->copy()->addDay()->toDateString(),
        'type' => AttendanceType::CheckIn,
    ]);
    markPresent($this->student, $this->monday->copy()->addDays(2));

    runAbsenceCommand($this->monday->copy()->addDays(2));

    expect(PrayerAbsenceAlert::withoutGlobalScope('school')->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('a student who is not covered is never warned', function () {
    Queue::fake();

    $this->student->forceFill(['religion' => Religion::Kristen])->save();

    foreach ([0, 1, 2] as $offset) {
        markPresent($this->student, $this->monday->copy()->addDays($offset));
    }

    runAbsenceCommand($this->monday->copy()->addDays(2));

    expect(PrayerAbsenceAlert::withoutGlobalScope('school')->count())->toBe(0);
});

test('an explicit per-student opt-out beats the school rule', function () {
    Queue::fake();

    $this->student->forceFill(['prayer_opt_in' => false])->save();

    foreach ([0, 1, 2] as $offset) {
        markPresent($this->student, $this->monday->copy()->addDays($offset));
    }

    runAbsenceCommand($this->monday->copy()->addDays(2));

    expect(PrayerAbsenceAlert::withoutGlobalScope('school')->count())->toBe(0);
});

test('the threshold is configurable per school', function () {
    Queue::fake();

    $this->school->setSetting('prayer_absence_threshold', 5);

    foreach (range(0, 4) as $offset) {
        markPresent($this->student, $this->monday->copy()->addDays($offset));
    }

    runAbsenceCommand($this->monday->copy()->addDays(2));
    expect(PrayerAbsenceAlert::withoutGlobalScope('school')->count())->toBe(0);

    runAbsenceCommand($this->monday->copy()->addDays(4));
    expect(PrayerAbsenceAlert::withoutGlobalScope('school')->count())->toBe(1);
});

test('a school with the feature switched off is skipped entirely', function () {
    Queue::fake();

    $this->school->setSetting('feature_notif_alpa_sholat', false);

    foreach ([0, 1, 2] as $offset) {
        markPresent($this->student, $this->monday->copy()->addDays($offset));
    }

    runAbsenceCommand($this->monday->copy()->addDays(2));

    expect(PrayerAbsenceAlert::withoutGlobalScope('school')->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('a student without any destination produces no alert at all', function () {
    Queue::fake();

    // Bukan alert yang gagal terkirim: baris ber-notified_at akan membungkam
    // peringatan yang tidak pernah sampai ke siapa pun.
    $this->parent->forceFill([
        'email' => null,
        'whatsapp_number' => '',
        'telegram_chat_id' => null,
    ])->save();

    foreach ([0, 1, 2] as $offset) {
        markPresent($this->student, $this->monday->copy()->addDays($offset));
    }

    runAbsenceCommand($this->monday->copy()->addDays(2));

    expect(PrayerAbsenceAlert::withoutGlobalScope('school')->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('a dry run writes nothing and queues nothing', function () {
    Queue::fake();

    foreach ([0, 1, 2] as $offset) {
        markPresent($this->student, $this->monday->copy()->addDays($offset));
    }

    runAbsenceCommand($this->monday->copy()->addDays(2), ['--dry-run' => true]);

    expect(PrayerAbsenceAlert::withoutGlobalScope('school')->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('running the command again the next day does not warn twice', function () {
    Queue::fake();

    foreach (range(0, 3) as $offset) {
        markPresent($this->student, $this->monday->copy()->addDays($offset));
    }

    runAbsenceCommand($this->monday->copy()->addDays(2));
    runAbsenceCommand($this->monday->copy()->addDays(3));

    Queue::assertPushed(SendPrayerAbsenceNotification::class, 1);
});

test('only dzuhur is evaluated when dhuha is switched off', function () {
    Queue::fake();

    foreach ([0, 1, 2] as $offset) {
        markPresent($this->student, $this->monday->copy()->addDays($offset));
    }

    runAbsenceCommand($this->monday->copy()->addDays(2));

    $types = PrayerAbsenceAlert::withoutGlobalScope('school')->pluck('prayer_type')->all();

    expect($types)->toBe([PrayerType::Dzuhur]);
});

test('two missed prayer types are merged into a single message', function () {
    Queue::fake();

    $this->school->setSetting('prayer_dhuha_enabled', true);

    foreach ([0, 1, 2] as $offset) {
        markPresent($this->student, $this->monday->copy()->addDays($offset));
    }

    runAbsenceCommand($this->monday->copy()->addDays(2));

    $alerts = PrayerAbsenceAlert::withoutGlobalScope('school')->get();

    expect($alerts)->toHaveCount(2)
        ->and($alerts->whereNotNull('notified_at'))->toHaveCount(2);

    // Dua WhatsApp nyaris identik dalam satu detik terbaca sebagai spam.
    Queue::assertPushed(SendPrayerAbsenceNotification::class, 1);

    $primary = $alerts->firstWhere('combined_types', '!=', null);

    expect($primary->combined_types)->toContain('Dhuha')
        ->and($primary->combined_types)->toContain('Dzuhur');
});

test('the job goes to the queue the supervisor actually listens to', function () {
    Queue::fake();

    foreach ([0, 1, 2] as $offset) {
        markPresent($this->student, $this->monday->copy()->addDays($offset));
    }

    runAbsenceCommand($this->monday->copy()->addDays(2));

    Queue::assertPushed(
        SendPrayerAbsenceNotification::class,
        fn (SendPrayerAbsenceNotification $job) => $job->queue === config('whatsapp.queue'),
    );
});

// ---------------------------------------------------------------- Pengiriman

function makeAlert(): PrayerAbsenceAlert
{
    return PrayerAbsenceAlert::withoutGlobalScope('school')->create([
        'school_id' => test()->school->id,
        'student_id' => test()->student->id,
        'prayer_type' => PrayerType::Dzuhur,
        'streak_start_date' => test()->monday->toDateString(),
        'streak_last_date' => test()->monday->copy()->addDays(2)->toDateString(),
        'streak_length' => 3,
        'notified_at' => now(),
    ]);
}

test('email is sent even when no EMAIL channel is registered', function () {
    Mail::fake();

    // Inilah arti "default lewat email": DefaultEmailGateway jatuh ke mailer
    // global kalau sekolah belum mengatur SMTP sendiri.
    app(NotificationDispatcher::class)->dispatchPrayerAbsence(makeAlert());

    Mail::assertSent(PrayerAbsenceNotificationMail::class, 1);
});

test('the log carries the alert id and a null attendance id', function () {
    Mail::fake();

    $alert = makeAlert();
    app(NotificationDispatcher::class)->dispatchPrayerAbsence($alert);

    $log = NotificationLog::withoutGlobalScope('school')->first();

    expect($log->prayer_absence_alert_id)->toBe($alert->id)
        ->and($log->attendance_id)->toBeNull()
        ->and($log->template_key)->toBe('prayer_absence_notify');
});

test('dispatching twice does not duplicate the log or resend', function () {
    Mail::fake();

    $alert = makeAlert();
    $dispatcher = app(NotificationDispatcher::class);

    $dispatcher->dispatchPrayerAbsence($alert);
    $dispatcher->dispatchPrayerAbsence($alert, 2);

    // Dulu `where('attendance_id', null)` menghasilkan `= NULL` yang tidak
    // pernah cocok, sehingga tiap retry mengirim ulang pesannya.
    expect(NotificationLog::withoutGlobalScope('school')
        ->where('channel', NotificationChannel::Email->value)
        ->count())->toBe(1);

    Mail::assertSent(PrayerAbsenceNotificationMail::class, 1);
});

test('one sent log does not silence another students alert', function () {
    Mail::fake();

    $alert = makeAlert();

    $otherStudent = Student::factory()->create([
        'school_id' => $this->school->id,
        'parent_profile_id' => $this->parent->id,
        'religion' => Religion::Islam,
    ]);

    $otherAlert = PrayerAbsenceAlert::withoutGlobalScope('school')->create([
        'school_id' => $this->school->id,
        'student_id' => $otherStudent->id,
        'prayer_type' => PrayerType::Dzuhur,
        'streak_start_date' => $this->monday->toDateString(),
        'streak_last_date' => $this->monday->copy()->addDays(2)->toDateString(),
        'streak_length' => 3,
        'notified_at' => now(),
    ]);

    $dispatcher = app(NotificationDispatcher::class);
    $dispatcher->dispatchPrayerAbsence($alert);
    $dispatcher->dispatchPrayerAbsence($otherAlert);

    // `whereNull('attendance_id')` akan mencocokkan SEMUA log alpa sholat dan
    // mendiamkan seluruh sekolah setelah satu pesan terkirim.
    Mail::assertSent(PrayerAbsenceNotificationMail::class, 2);
});

test('whatsapp only goes out when the school has an active WA channel', function () {
    Mail::fake();

    app(NotificationDispatcher::class)->dispatchPrayerAbsence(makeAlert());

    expect(NotificationLog::withoutGlobalScope('school')
        ->where('channel', NotificationChannel::Whatsapp->value)
        ->count())->toBe(0);

    SchoolNotificationChannel::create([
        'school_id' => $this->school->id,
        'channel' => SchoolChannelType::FonnteWa,
        'is_active' => true,
        'settings' => ['fonnte_token' => 'x'],
    ]);

    $second = PrayerAbsenceAlert::withoutGlobalScope('school')->create([
        'school_id' => $this->school->id,
        'student_id' => $this->student->id,
        'prayer_type' => PrayerType::Dhuha,
        'streak_start_date' => $this->monday->toDateString(),
        'streak_last_date' => $this->monday->copy()->addDays(2)->toDateString(),
        'streak_length' => 3,
        'notified_at' => now(),
    ]);

    app(NotificationDispatcher::class)->dispatchPrayerAbsence($second);

    expect(NotificationLog::withoutGlobalScope('school')
        ->where('channel', NotificationChannel::Whatsapp->value)
        ->count())->toBe(1);
});
