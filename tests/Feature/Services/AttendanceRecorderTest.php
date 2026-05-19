<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Events\StudentCheckedIn;
use App\Events\StudentCheckedOut;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\Student;
use App\Services\Attendance\AttendanceRecorder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([StudentCheckedIn::class, StudentCheckedOut::class]);
    $this->student = Student::factory()->create();

    // Create schedule for today
    AttendanceSchedule::factory()->create([
        'classroom_id' => $this->student->classroom_id,
        'day_of_week' => Carbon::now()->dayOfWeekIso,
        'check_in_start' => '06:30',
        'check_in_end' => '09:00',
        'late_threshold' => '07:15',
        'check_out_start' => '14:00',
        'check_out_end' => '16:00',
    ]);
});

test('records check-in as hadir when on time', function () {
    $recorder = app(AttendanceRecorder::class);
    $timestamp = Carbon::today()->setTime(7, 10);

    $result = $recorder->record($this->student, AttendanceType::CheckIn, timestamp: $timestamp);

    expect($result['success'])->toBeTrue();
    expect($result['attendance']->status)->toBe(AttendanceStatus::Hadir);
});

test('records check-in as terlambat when past threshold', function () {
    $recorder = app(AttendanceRecorder::class);
    $timestamp = Carbon::today()->setTime(7, 30);

    $result = $recorder->record($this->student, AttendanceType::CheckIn, timestamp: $timestamp);

    expect($result['success'])->toBeTrue();
    expect($result['attendance']->status)->toBe(AttendanceStatus::Terlambat);
});

test('prevents double check-in on same day', function () {
    $recorder = app(AttendanceRecorder::class);
    $timestamp = Carbon::today()->setTime(7, 5);

    $first = $recorder->record($this->student, AttendanceType::CheckIn, timestamp: $timestamp);
    expect($first['success'])->toBeTrue();

    $second = $recorder->record($this->student, AttendanceType::CheckIn, timestamp: $timestamp->addMinutes(5));
    expect($second['success'])->toBeFalse();
    expect($second['message'])->toContain('sudah melakukan check-in');
});

test('records check-out after check-in', function () {
    $recorder = app(AttendanceRecorder::class);

    $recorder->record($this->student, AttendanceType::CheckIn, timestamp: Carbon::today()->setTime(7, 5));
    $result = $recorder->record($this->student, AttendanceType::CheckOut, timestamp: Carbon::today()->setTime(14, 30));

    expect($result['success'])->toBeTrue();
    expect($result['attendance']->type)->toBe(AttendanceType::CheckOut);
    expect($result['attendance']->status)->toBe(AttendanceStatus::Hadir);
});

test('fails when no schedule exists for the day', function () {
    $recorder = app(AttendanceRecorder::class);
    // Use a Sunday where no schedule exists
    $sunday = Carbon::now()->next(Carbon::SUNDAY);

    $result = $recorder->record($this->student, AttendanceType::CheckIn, timestamp: $sunday->setTime(7, 5));

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('jadwal');
});
