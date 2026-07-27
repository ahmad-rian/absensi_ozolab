<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Events\StudentCheckedIn;
use App\Events\StudentCheckedOut;
use App\Models\AttendanceSchedule;
use App\Models\Student;
use App\Services\Attendance\AttendanceRecorder;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

/**
 * Jadwal default aplikasi: masuk 06:00, telat > 08:00, pulang 13:00–18:00.
 */
beforeEach(function () {
    Event::fake([StudentCheckedIn::class, StudentCheckedOut::class]);

    $this->monday = SchoolTime::now()->startOfWeek(Carbon::MONDAY)->addWeek();
    $this->student = Student::factory()->create();

    foreach (range(1, 5) as $day) {
        AttendanceSchedule::factory()->create([
            'classroom_id' => $this->student->classroom_id,
            'day_of_week' => $day,
        ]);
    }

    $this->recorder = app(AttendanceRecorder::class);
});

function mondayAt(int $hour, int $minute = 0): Carbon
{
    return test()->monday->copy()->setTime($hour, $minute);
}

test('records check-in as hadir when on time', function () {
    $result = $this->recorder->record($this->student, timestamp: mondayAt(7, 10));

    expect($result['success'])->toBeTrue()
        ->and($result['attendance']->type)->toBe(AttendanceType::CheckIn)
        ->and($result['attendance']->status)->toBe(AttendanceStatus::Hadir);
});

test('records check-in as terlambat when past threshold', function () {
    $result = $this->recorder->record($this->student, timestamp: mondayAt(8, 30));

    expect($result['success'])->toBeTrue()
        ->and($result['attendance']->type)->toBe(AttendanceType::CheckIn)
        ->and($result['attendance']->status)->toBe(AttendanceStatus::Terlambat);
});

test('rejects a scan before the check-in window opens', function () {
    $result = $this->recorder->record($this->student, timestamp: mondayAt(5, 59));

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Belum waktunya absen');
});

test('rejects a scan after the check-out window closes', function () {
    $result = $this->recorder->record($this->student, timestamp: mondayAt(18, 1));

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('sudah tutup');
});

test('a second scan inside the check-in window is rejected, not converted to check-out', function () {
    $first = $this->recorder->record($this->student, timestamp: mondayAt(7, 5));
    expect($first['success'])->toBeTrue();

    $second = $this->recorder->record($this->student, timestamp: mondayAt(7, 10));

    expect($second['success'])->toBeFalse()
        ->and($second['message'])->toContain('Sudah absen masuk')
        ->and($this->student->attendances()->where('type', AttendanceType::CheckOut)->count())->toBe(0);
});

test('resolves check-out from the afternoon window', function () {
    $this->recorder->record($this->student, timestamp: mondayAt(7, 5));
    $result = $this->recorder->record($this->student, timestamp: mondayAt(14, 30));

    expect($result['success'])->toBeTrue()
        ->and($result['attendance']->type)->toBe(AttendanceType::CheckOut)
        ->and($result['attendance']->status)->toBe(AttendanceStatus::Hadir);
});

test('check-out is allowed even when the student never checked in', function () {
    $result = $this->recorder->record($this->student, timestamp: mondayAt(14, 0));

    expect($result['success'])->toBeTrue()
        ->and($result['attendance']->type)->toBe(AttendanceType::CheckOut);
});

test('the next morning is a fresh check-in when yesterday was never closed', function () {
    $monday = $this->recorder->record($this->student, timestamp: mondayAt(7, 0));
    expect($monday['attendance']->type)->toBe(AttendanceType::CheckIn);

    $tuesday = $this->recorder->record($this->student, timestamp: mondayAt(7, 0)->addDay());

    expect($tuesday['success'])->toBeTrue()
        ->and($tuesday['attendance']->type)->toBe(AttendanceType::CheckIn)
        ->and($this->student->attendances()->where('type', AttendanceType::CheckIn)->count())->toBe(2);
});

test('an explicit type overrides the time window', function () {
    $result = $this->recorder->record(
        $this->student,
        type: AttendanceType::CheckOut,
        timestamp: mondayAt(7, 0),
    );

    expect($result['success'])->toBeTrue()
        ->and($result['attendance']->type)->toBe(AttendanceType::CheckOut);
});

test('fails when no schedule exists for the day', function () {
    $sunday = $this->monday->copy()->addDays(6)->setTime(7, 5);

    $result = $this->recorder->record($this->student, timestamp: $sunday);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('jadwal');
});
