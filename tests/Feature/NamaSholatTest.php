<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Enums\PrayerType;
use App\Enums\Religion;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\ParentProfile;
use App\Models\PrayerAbsenceAlert;
use App\Models\School;
use App\Models\Student;
use App\Services\Attendance\PrayerAttendanceRecorder;
use App\Support\PrayerSettings;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;

/**
 * Nama tampilan Dhuha / Dzuhur bisa diganti per sekolah tanpa mengubah
 * nilai enum maupun data yang sudah tersimpan.
 */
function sekolahSholat(array $settings = []): School
{
    $school = School::factory()->create([
        'is_active' => true,
        'settings' => [
            'prayer_enabled' => true,
            'prayer_dhuha_enabled' => true,
            ...$settings,
        ],
    ]);

    foreach (range(1, 5) as $day) {
        AttendanceSchedule::factory()->create([
            'school_id' => $school->id,
            'classroom_id' => null,
            'day_of_week' => $day,
            'is_active' => true,
        ]);
    }

    return $school;
}

test('label falls back to the default name without a custom one', function () {
    $school = sekolahSholat();

    expect(PrayerType::Dhuha->label($school))->toBe('Sholat Dhuha')
        ->and(PrayerType::Dzuhur->shortLabel($school))->toBe('Dzuhur')
        ->and(PrayerType::Dhuha->label())->toBe('Sholat Dhuha');
});

test('a custom name is used and not prefixed twice', function () {
    $school = sekolahSholat([
        'prayer_dhuha_nama' => 'Duha Berjamaah',
        'prayer_nama' => 'Shalat Zuhur',
    ]);

    expect(PrayerType::Dhuha->label($school))->toBe('Sholat Duha Berjamaah')
        ->and(PrayerType::Dhuha->shortLabel($school))->toBe('Duha Berjamaah')
        ->and(PrayerType::Dzuhur->label($school))->toBe('Shalat Zuhur')
        ->and(PrayerSettings::for($school, PrayerType::Dzuhur)->toArray())
        ->toMatchArray(['type_label' => 'Shalat Zuhur', 'type_short' => 'Shalat Zuhur', 'nama' => 'Shalat Zuhur']);
});

test('another school keeps the default name', function () {
    sekolahSholat(['prayer_dhuha_nama' => 'Duha']);
    $lain = sekolahSholat();

    expect(PrayerType::Dhuha->label($lain))->toBe('Sholat Dhuha');
});

test('the scan message uses the school name for the prayer', function () {
    $school = sekolahSholat(['prayer_dhuha_nama' => 'Duha']);
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'religion' => Religion::Islam,
    ]);
    $senin = SchoolTime::now()->startOfWeek(Carbon::MONDAY)->addWeek()->setTime(7, 45);

    $result = app(PrayerAttendanceRecorder::class)->record($student, timestamp: $senin);
    $ulang = app(PrayerAttendanceRecorder::class)->record($student, timestamp: $senin->copy()->addMinutes(5));

    expect($result['message'])->toBe('Absen Sholat Duha berhasil dicatat.')
        ->and($result['attendance']->prayer_type)->toBe(PrayerType::Dhuha)
        ->and($ulang['message'])->toContain('Sudah absen Sholat Duha');
});

test('the whatsapp absence alert carries the custom names', function () {
    Queue::fake();
    $school = sekolahSholat([
        'feature_notif_alpa_sholat' => true,
        'prayer_absence_threshold' => 3,
        'prayer_absence_require_present' => true,
        'prayer_dhuha_nama' => 'Duha',
        'prayer_nama' => 'Zuhur',
    ]);
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'religion' => Religion::Islam,
        'is_active' => true,
        'parent_profile_id' => ParentProfile::factory()->create([
            'school_id' => $school->id,
            'email' => 'ortu@example.test',
        ])->id,
    ]);
    $senin = SchoolTime::now()->startOfWeek(Carbon::MONDAY)->subWeek()->startOfDay();

    foreach ([0, 1, 2] as $offset) {
        Attendance::factory()->create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'attendance_date' => $senin->copy()->addDays($offset)->toDateString(),
            'type' => AttendanceType::CheckIn,
            'status' => AttendanceStatus::Hadir,
            'recorded_at' => $senin->copy()->addDays($offset)->setTime(7, 0),
        ]);
    }

    $this->artisan('prayer:notify-absence', ['--date' => $senin->copy()->addDays(2)->toDateString()])
        ->assertSuccessful();

    $primary = PrayerAbsenceAlert::withoutGlobalScope('school')->whereNotNull('combined_types')->first();

    expect($primary->combined_types)->toBe(['Duha', 'Zuhur']);
});

test('admin can rename the prayers from the sholat settings tab', function () {
    $user = createAdminUser();

    $this->actingAs($user)->put(route('admin.pengaturan.update'), [
        'section' => 'sholat',
        'prayer_dhuha_enabled' => true,
        'prayer_enabled' => true,
        'prayer_all_religions' => false,
        'prayer_dhuha_nama' => '  Duha  ',
        'prayer_nama' => '',
    ])->assertSessionHasNoErrors();

    $school = School::find($user->school_id);

    expect($school->getSetting('prayer_dhuha_nama'))->toBe('Duha')
        ->and(PrayerType::Dhuha->label($school))->toBe('Sholat Duha')
        ->and(PrayerType::Dzuhur->label($school))->toBe('Sholat Dzuhur');

    $this->actingAs($user)->get(route('admin.pengaturan'))
        ->assertInertia(fn ($page) => $page
            ->where('settings.prayer_dhuha_nama', 'Duha')
            ->where('namaSholat.dhuha', 'Duha')
            ->where('namaSholat.dzuhur', 'Dzuhur'));
});

test('prayer names that are too long or carry odd characters are rejected', function (string $nama) {
    $user = createAdminUser();

    $this->actingAs($user)->put(route('admin.pengaturan.update'), [
        'section' => 'sholat',
        'prayer_dhuha_enabled' => true,
        'prayer_enabled' => true,
        'prayer_all_religions' => false,
        'prayer_dhuha_nama' => $nama,
    ])->assertSessionHasErrors('prayer_dhuha_nama');
})->with([
    'terlalu panjang' => [str_repeat('a', PrayerType::NAMA_MAKS + 1)],
    'baris baru' => ["Duha\nhttps://x.test"],
    'tag html' => ['<b>Duha</b>'],
]);
