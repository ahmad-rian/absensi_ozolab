<?php

use App\Enums\AttendanceStatus;
use App\Enums\Religion;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\PrayerAttendance;
use App\Models\School;
use App\Models\Student;
use App\Services\Attendance\PrayerAttendanceRecorder;
use App\Support\SchoolTime;
use Carbon\Carbon;

beforeEach(function () {
    $this->monday = SchoolTime::now()->startOfWeek(Carbon::MONDAY)->addWeek()->startOfDay();

    $this->school = School::factory()->create([
        'settings' => ['prayer_enabled' => true],
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

function prayerAt(int $hour, int $minute = 0): Carbon
{
    return test()->monday->copy()->setTime($hour, $minute);
}

test('records a prayer check-in inside the window', function () {
    $result = $this->recorder->record($this->student, timestamp: prayerAt(11, 30));

    expect($result['success'])->toBeTrue()
        ->and($result['attendance']->status)->toBe(AttendanceStatus::Hadir)
        ->and($result['attendance']->prayer_date->toDateString())->toBe($this->monday->toDateString());
});

test('never writes to the school attendance table', function () {
    $this->recorder->record($this->student, timestamp: prayerAt(11, 30));

    expect(Attendance::count())->toBe(0)
        ->and(PrayerAttendance::count())->toBe(1);
});

test('rejects a scan before the window opens', function () {
    $result = $this->recorder->record($this->student, timestamp: prayerAt(10, 59));

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('11:00');
});

test('rejects a scan after the window closes', function () {
    $result = $this->recorder->record($this->student, timestamp: prayerAt(13, 1));

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('13:00');
});

test('only allows one check-in per day', function () {
    $first = $this->recorder->record($this->student, timestamp: prayerAt(11, 10));
    expect($first['success'])->toBeTrue();

    $second = $this->recorder->record($this->student, timestamp: prayerAt(12, 0));

    // Pesannya menyebut jenis sholat sejak Dhuha ditambahkan — "sudah absen
    // sholat hari ini" jadi menyesatkan ketika ada dua jenis.
    expect($second['success'])->toBeFalse()
        ->and($second['message'])->toContain('Sudah absen Sholat Dzuhur hari ini pukul 11:10')
        ->and(PrayerAttendance::count())->toBe(1);
});

test('the next day is a fresh check-in', function () {
    $this->recorder->record($this->student, timestamp: prayerAt(11, 10));
    $result = $this->recorder->record($this->student, timestamp: prayerAt(11, 10)->addDay());

    expect($result['success'])->toBeTrue()
        ->and(PrayerAttendance::count())->toBe(2);
});

test('rejects non-muslim students by default', function () {
    $kristen = Student::factory()->create([
        'school_id' => $this->school->id,
        'religion' => Religion::Kristen,
    ]);

    $result = $this->recorder->record($kristen, timestamp: prayerAt(11, 30));

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('beragama Islam');
});

test('accepts non-muslim students when the school opts in', function () {
    $this->school->setSetting('prayer_all_religions', true);

    $kristen = Student::factory()->create([
        'school_id' => $this->school->id,
        'religion' => Religion::Kristen,
    ]);

    $result = $this->recorder->record($kristen->fresh(), timestamp: prayerAt(11, 30));

    expect($result['success'])->toBeTrue();
});

test('rejects everything when the feature is off', function () {
    $this->school->setSetting('prayer_enabled', false);

    $result = $this->recorder->record($this->student->fresh(), timestamp: prayerAt(11, 30));

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('belum diaktifkan');
});

test('rejects a day without an active schedule', function () {
    $sunday = $this->monday->copy()->addDays(6)->setTime(11, 30);

    $result = $this->recorder->record($this->student, timestamp: $sunday);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('jadwal');
});

test('honours a custom window set by the school', function () {
    $this->school->setSetting('prayer_start', '12:00');
    $this->school->setSetting('prayer_end', '12:30');

    $student = $this->student->fresh();

    expect($this->recorder->record($student, timestamp: prayerAt(11, 30))['success'])->toBeFalse()
        ->and($this->recorder->record($student, timestamp: prayerAt(12, 15))['success'])->toBeTrue();
});

test('a per-student opt-in overrides the school rule', function () {
    $kristen = Student::factory()->create([
        'school_id' => $this->school->id,
        'religion' => Religion::Kristen,
        'prayer_opt_in' => true,
    ]);

    $result = $this->recorder->record($kristen, timestamp: prayerAt(11, 30));

    expect($result['success'])->toBeTrue();
});

test('a per-student opt-out excludes a muslim student', function () {
    $this->student->update(['prayer_opt_in' => false]);

    $result = $this->recorder->record($this->student->fresh(), timestamp: prayerAt(11, 30));

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('beragama Islam');
});

test('a null opt-in keeps following the school rule', function () {
    $kristen = Student::factory()->create([
        'school_id' => $this->school->id,
        'religion' => Religion::Kristen,
        'prayer_opt_in' => null,
    ]);

    expect($this->recorder->record($kristen, timestamp: prayerAt(11, 30))['success'])->toBeFalse();

    $this->school->setSetting('prayer_all_religions', true);

    expect($this->recorder->record($kristen->fresh(), timestamp: prayerAt(11, 40))['success'])->toBeTrue();
});
