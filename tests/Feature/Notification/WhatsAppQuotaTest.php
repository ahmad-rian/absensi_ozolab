<?php

use App\Enums\AttendanceAlertKind;
use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\SchoolChannelType;
use App\Models\AttendanceAlert;
use App\Models\NotificationLog;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\SchoolNotificationChannel;
use App\Models\Student;
use App\Services\Notification\NotificationDispatcher;
use App\Support\SchoolTime;
use App\Support\WhatsAppQuota;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->travelTo(Carbon::create(2026, 8, 24, 14, 0, 0, SchoolTime::timezone()));

    $this->school = School::factory()->create([
        'is_active' => true,
        'settings' => ['whatsapp_enabled' => true, 'wa_alert_only' => true],
    ]);

    SchoolNotificationChannel::create([
        'school_id' => $this->school->id,
        'channel' => SchoolChannelType::OzolabWa,
        'is_active' => true,
    ]);

    $this->parent = ParentProfile::factory()->create([
        'school_id' => $this->school->id,
        'whatsapp_number' => '081200000001',
    ]);

    $this->student = Student::factory()->create([
        'school_id' => $this->school->id,
        'parent_profile_id' => $this->parent->id,
        'is_active' => true,
    ]);
});

/**
 * Isi kuota dengan log yang sudah terkirim hari ini.
 */
function isiKuota(School $school, Student $student, ParentProfile $parent, int $jumlah, ?Carbon $sentAt = null): void
{
    NotificationLog::factory()->count($jumlah)->create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'parent_profile_id' => $parent->id,
        'channel' => NotificationChannel::Whatsapp,
        'status' => NotificationStatus::Sent,
        'sent_at' => $sentAt ?? now(),
    ]);
}

function buatAlert(School $school, Student $student): AttendanceAlert
{
    return AttendanceAlert::withoutGlobalScope('school')->create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'alert_date' => SchoolTime::todayString(),
        'kind' => AttendanceAlertKind::Alpa,
        'notified_at' => now(),
    ]);
}

test('pesan yang melewati batas tidak pernah sampai ke gateway', function () {
    Http::fake(['wa.ozolab.id/*' => Http::response(['status' => true])]);

    isiKuota($this->school, $this->student, $this->parent, WhatsAppQuota::DEFAULT_LIMIT);

    $succeeded = app(NotificationDispatcher::class)
        ->dispatchAttendanceAlert(buatAlert($this->school, $this->student));

    Http::assertNothingSent();

    $log = NotificationLog::withoutGlobalScope('school')
        ->where('template_key', 'attendance_alert_notify')
        ->firstOrFail();

    expect($log->status)->toBe(NotificationStatus::Throttled)
        ->and($log->sent_at)->toBeNull()
        ->and($log->error_message)->toContain('50');

    // Kuota habis bukan kegagalan sementara: mengembalikan false membuat job
    // mengulang tiga kali untuk hasil yang sudah pasti sama.
    expect($succeeded)->toBeTrue();
});

test('satu pesan di bawah batas masih terkirim', function () {
    Http::fake(['wa.ozolab.id/*' => Http::response(['status' => true])]);

    isiKuota($this->school, $this->student, $this->parent, WhatsAppQuota::DEFAULT_LIMIT - 1);

    app(NotificationDispatcher::class)->dispatchAttendanceAlert(buatAlert($this->school, $this->student));

    Http::assertSentCount(1);

    expect(NotificationLog::withoutGlobalScope('school')
        ->where('template_key', 'attendance_alert_notify')
        ->firstOrFail()
        ->status)->toBe(NotificationStatus::Sent);
});

test('nomor yang sudah terverifikasi tidak dibatasi', function () {
    Http::fake(['wa.ozolab.id/*' => Http::response(['status' => true])]);

    $this->school->setSetting('wa_verified', true);
    $this->school->save();

    isiKuota($this->school, $this->student, $this->parent, WhatsAppQuota::DEFAULT_LIMIT + 10);

    app(NotificationDispatcher::class)->dispatchAttendanceAlert(buatAlert($this->school, $this->student));

    Http::assertSentCount(1);
});

test('batas yang diatur sendiri dihormati', function () {
    Http::fake(['wa.ozolab.id/*' => Http::response(['status' => true])]);

    $this->school->setSetting('wa_daily_limit', 10);
    $this->school->save();

    isiKuota($this->school, $this->student, $this->parent, 10);

    app(NotificationDispatcher::class)->dispatchAttendanceAlert(buatAlert($this->school, $this->student));

    Http::assertNothingSent();
});

test('batas tak masuk akal dikembalikan ke bawaan', function () {
    foreach ([0, -5, 'entah'] as $nilai) {
        $this->school->setSetting('wa_daily_limit', $nilai);
        $this->school->save();

        expect(WhatsAppQuota::limitFor($this->school->fresh()))->toBe(WhatsAppQuota::DEFAULT_LIMIT);
    }
});

test('log milik sekolah lain tidak ikut terhitung', function () {
    $lain = School::factory()->create();
    $ortuLain = ParentProfile::factory()->create(['school_id' => $lain->id]);
    $siswaLain = Student::factory()->create([
        'school_id' => $lain->id,
        'parent_profile_id' => $ortuLain->id,
    ]);

    isiKuota($lain, $siswaLain, $ortuLain, WhatsAppQuota::DEFAULT_LIMIT);

    expect(WhatsAppQuota::sentToday($this->school->id))->toBe(0)
        ->and(WhatsAppQuota::sentToday($lain->id))->toBe(WhatsAppQuota::DEFAULT_LIMIT);
});

/**
 * `sent_at` ditulis dengan `now()` yang tunduk pada config('app.timezone') = UTC,
 * sementara "hari ini" bagi sekolah adalah jam dinding WIB. Pesan pukul 23.30
 * WIB kemarin = 16.30 UTC kemarin; kalau batas harinya dihitung memakai
 * `whereDate()` apa adanya, ia bocor ke hitungan hari ini dan kuota terasa
 * habis tujuh jam terlalu cepat.
 */
test('pesan kemarin malam waktu sekolah tidak ikut terhitung hari ini', function () {
    isiKuota(
        $this->school,
        $this->student,
        $this->parent,
        5,
        SchoolTime::today()->subDay()->setTime(23, 30)->utc(),
    );

    expect(WhatsAppQuota::sentToday($this->school->id))->toBe(0);
});

test('pesan tengah malam hari ini waktu sekolah ikut terhitung', function () {
    isiKuota(
        $this->school,
        $this->student,
        $this->parent,
        3,
        SchoolTime::today()->setTime(0, 15)->utc(),
    );

    expect(WhatsAppQuota::sentToday($this->school->id))->toBe(3);
});

test('log yang gagal tidak memakan kuota', function () {
    NotificationLog::factory()->count(50)->create([
        'school_id' => $this->school->id,
        'student_id' => $this->student->id,
        'parent_profile_id' => $this->parent->id,
        'channel' => NotificationChannel::Whatsapp,
        'status' => NotificationStatus::Failed,
        'sent_at' => null,
    ]);

    expect(WhatsAppQuota::sentToday($this->school->id))->toBe(0);
});

test('email dan telegram tidak memakan kuota WhatsApp', function () {
    foreach ([NotificationChannel::Email, NotificationChannel::Telegram] as $channel) {
        NotificationLog::factory()->count(30)->create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'parent_profile_id' => $this->parent->id,
            'channel' => $channel,
            'status' => NotificationStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    expect(WhatsAppQuota::sentToday($this->school->id))->toBe(0);
});
