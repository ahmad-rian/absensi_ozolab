<?php

use App\Enums\PrayerType;
use App\Enums\Religion;
use App\Models\AttendanceSchedule;
use App\Models\PrayerAttendance;
use App\Models\School;
use App\Models\Student;
use App\Services\Attendance\PrayerAttendanceRecorder;
use App\Support\PrayerSchedule;
use App\Support\PrayerSettings;
use App\Support\SchoolTime;
use Carbon\Carbon;

/**
 * Satu URL scan melayani dua jenis sholat; jam scan yang menentukan jenisnya.
 */
beforeEach(function () {
    // Dihitung sebelum travelTo apa pun, supaya rentangnya tidak bergeser.
    $this->monday = SchoolTime::now()->startOfWeek(Carbon::MONDAY)->addWeek()->startOfDay();

    $this->school = School::factory()->create([
        'settings' => [
            'prayer_enabled' => true,
            'prayer_dhuha_enabled' => true,
        ],
    ]);

    $this->student = Student::factory()->create([
        'school_id' => $this->school->id,
        'religion' => Religion::Islam,
    ]);

    foreach (range(1, 5) as $day) {
        AttendanceSchedule::factory()->create([
            'school_id' => $this->school->id,
            'classroom_id' => null,
            'day_of_week' => $day,
            'is_active' => true,
        ]);
    }

    $this->recorder = app(PrayerAttendanceRecorder::class);
});

function dhuhaAt(int $hour, int $minute = 0): Carbon
{
    return test()->monday->copy()->setTime($hour, $minute);
}

test('a scan inside the dhuha window is recorded as dhuha', function () {
    $result = $this->recorder->record($this->student, timestamp: dhuhaAt(7, 45));

    expect($result['success'])->toBeTrue()
        ->and($result['attendance']->prayer_type)->toBe(PrayerType::Dhuha)
        ->and($result['attendance']->device_id)->toBe('PRAYER-SCAN-DHUHA')
        ->and($result['message'])->toContain('Sholat Dhuha');
});

test('the prayer type is detected from the clock on a single url', function () {
    $dhuha = $this->recorder->record($this->student, timestamp: dhuhaAt(7, 45));
    $dzuhur = $this->recorder->record($this->student, timestamp: dhuhaAt(11, 30));

    expect($dhuha['success'])->toBeTrue()
        ->and($dzuhur['success'])->toBeTrue()
        ->and(PrayerAttendance::count())->toBe(2)
        ->and($dhuha['attendance']->prayer_type)->toBe(PrayerType::Dhuha)
        ->and($dzuhur['attendance']->prayer_type)->toBe(PrayerType::Dzuhur);
});

test('an existing dzuhur row does not block a dhuha row', function () {
    PrayerAttendance::factory()->create([
        'school_id' => $this->school->id,
        'student_id' => $this->student->id,
        'prayer_date' => $this->monday->toDateString(),
        'prayer_type' => PrayerType::Dzuhur,
    ]);

    $result = $this->recorder->record($this->student, timestamp: dhuhaAt(8, 0));

    expect($result['success'])->toBeTrue()
        ->and(PrayerAttendance::count())->toBe(2);
});

test('one check-in per type per day', function () {
    $this->recorder->record($this->student, timestamp: dhuhaAt(7, 45));
    $second = $this->recorder->record($this->student, timestamp: dhuhaAt(8, 30));

    expect($second['success'])->toBeFalse()
        ->and($second['message'])->toContain('Sudah absen Sholat Dhuha hari ini pukul 07:45')
        ->and(PrayerAttendance::count())->toBe(1);
});

test('a scan between the two windows is rejected and names both', function () {
    $result = $this->recorder->record($this->student, timestamp: dhuhaAt(10, 0));

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('07:30')
        ->and($result['message'])->toContain('09:00')
        ->and($result['message'])->toContain('11:00')
        ->and($result['message'])->toContain('13:00')
        ->and(PrayerAttendance::count())->toBe(0);
});

test('dhuha stays off until the school enables it', function () {
    $solo = School::factory()->create(['settings' => ['prayer_enabled' => true]]);
    $student = Student::factory()->create(['school_id' => $solo->id, 'religion' => Religion::Islam]);

    foreach (range(1, 5) as $day) {
        AttendanceSchedule::factory()->create([
            'school_id' => $solo->id,
            'classroom_id' => null,
            'day_of_week' => $day,
            'is_active' => true,
        ]);
    }

    $result = $this->recorder->record($student, timestamp: dhuhaAt(7, 45));

    // Hanya Dzuhur yang aktif, jadi pesannya kembali ke bentuk lama.
    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe('Absen sholat dibuka pukul 11:00 s/d 13:00.')
        ->and(PrayerAttendance::count())->toBe(0);
});

test('an explicit dhuha record is refused when dhuha is disabled', function () {
    $solo = School::factory()->create(['settings' => ['prayer_enabled' => true]]);
    $student = Student::factory()->create(['school_id' => $solo->id, 'religion' => Religion::Islam]);

    foreach (range(1, 5) as $day) {
        AttendanceSchedule::factory()->create([
            'school_id' => $solo->id,
            'classroom_id' => null,
            'day_of_week' => $day,
            'is_active' => true,
        ]);
    }

    $result = $this->recorder->record($student, timestamp: dhuhaAt(7, 45), type: PrayerType::Dhuha);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('belum diaktifkan');
});

test('an explicit type outside its own window is still refused', function () {
    $result = $this->recorder->record($this->student, timestamp: dhuhaAt(11, 30), type: PrayerType::Dhuha);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe('Absen Sholat Dhuha dibuka pukul 07:30 s/d 09:00.');
});

test('the legacy keys still drive dzuhur', function () {
    $legacy = School::factory()->create([
        'settings' => [
            'prayer_enabled' => true,
            'prayer_start' => '12:00',
            'prayer_end' => '12:30',
        ],
    ]);

    $settings = PrayerSettings::for($legacy, PrayerType::Dzuhur);

    expect($settings->enabled)->toBeTrue()
        ->and($settings->displayStart())->toBe('12:00')
        ->and($settings->displayEnd())->toBe('12:30');
});

test('dhuha defaults to 07:30 until 09:00', function () {
    $settings = PrayerSettings::for($this->school, PrayerType::Dhuha);

    expect($settings->displayStart())->toBe('07:30')
        ->and($settings->displayEnd())->toBe('09:00');
});

test('overlapping windows are detected, touching minutes included', function () {
    $schedule = PrayerSchedule::for(School::factory()->create([
        'settings' => [
            'prayer_enabled' => true,
            'prayer_start' => '09:00',
            'prayer_dhuha_enabled' => true,
            'prayer_dhuha_end' => '09:00',
        ],
    ]));

    // Batas jendela inklusif di kedua ujung, jadi 09:00 selesai + 09:00 mulai
    // tetap ambigu bagi deteksi-jam.
    expect($schedule->overlapping())->toHaveCount(1);
});

test('adjacent windows without an intersection are fine', function () {
    expect(PrayerSchedule::for($this->school)->overlapping())->toBe([]);
});
