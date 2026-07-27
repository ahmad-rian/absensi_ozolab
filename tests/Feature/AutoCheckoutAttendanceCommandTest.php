<?php

use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use App\Support\SchoolTime;
use Carbon\Carbon;

beforeEach(function () {
    $this->monday = SchoolTime::now()->startOfWeek(Carbon::MONDAY)->addWeek()->startOfDay();
    $this->school = School::factory()->create();
    $this->classroom = Classroom::factory()->create(['school_id' => $this->school->id]);

    AttendanceSchedule::factory()->create([
        'school_id' => $this->school->id,
        'classroom_id' => null,
        'day_of_week' => 1,
        'is_active' => true,
    ]);
});

function makeStudent(): Student
{
    return Student::factory()->create([
        'school_id' => test()->school->id,
        'classroom_id' => test()->classroom->id,
    ]);
}

function checkInOnMonday(Student $student): Attendance
{
    return Attendance::factory()->create([
        'school_id' => $student->school_id,
        'student_id' => $student->id,
        'attendance_date' => test()->monday->toDateString(),
        'type' => AttendanceType::CheckIn,
        'recorded_at' => test()->monday->copy()->setTime(7, 0),
    ]);
}

test('closes a dangling check-in after the check-out window has passed', function () {
    $student = makeStudent();
    checkInOnMonday($student);

    $this->travelTo($this->monday->copy()->setTime(19, 0));

    $this->artisan('attendance:auto-checkout')->assertSuccessful();

    $checkOut = $student->attendances()->where('type', AttendanceType::CheckOut)->first();

    expect($checkOut)->not->toBeNull()
        ->and($checkOut->recorded_at->format('H:i'))->toBe('18:00')
        ->and($checkOut->device_id)->toBe('AUTO');
});

test('does nothing before the check-out window closes', function () {
    $student = makeStudent();
    checkInOnMonday($student);

    $this->travelTo($this->monday->copy()->setTime(15, 0));

    $this->artisan('attendance:auto-checkout')->assertSuccessful();

    expect($student->attendances()->where('type', AttendanceType::CheckOut)->count())->toBe(0);
});

test('is idempotent when run twice', function () {
    $student = makeStudent();
    checkInOnMonday($student);

    $this->travelTo($this->monday->copy()->setTime(19, 0));

    $this->artisan('attendance:auto-checkout')->assertSuccessful();
    $this->artisan('attendance:auto-checkout')->assertSuccessful();

    expect($student->attendances()->where('type', AttendanceType::CheckOut)->count())->toBe(1);
});

test('leaves students who already checked out untouched', function () {
    $student = makeStudent();
    checkInOnMonday($student);

    Attendance::factory()->checkOut()->create([
        'school_id' => $student->school_id,
        'student_id' => $student->id,
        'attendance_date' => $this->monday->toDateString(),
        'recorded_at' => $this->monday->copy()->setTime(14, 0),
        'device_id' => 'SCANNER-01',
    ]);

    $this->travelTo($this->monday->copy()->setTime(19, 0));

    $this->artisan('attendance:auto-checkout')->assertSuccessful();

    $checkOut = $student->attendances()->where('type', AttendanceType::CheckOut)->sole();

    expect($checkOut->recorded_at->format('H:i'))->toBe('14:00')
        ->and($checkOut->device_id)->toBe('SCANNER-01');
});

test('backfills an older date via --date', function () {
    $student = makeStudent();
    checkInOnMonday($student);

    // Sudah lewat beberapa hari, baris Senin masih menggantung.
    $this->travelTo($this->monday->copy()->addDays(3)->setTime(10, 0));

    $this->artisan('attendance:auto-checkout', ['--date' => $this->monday->toDateString()])
        ->assertSuccessful();

    expect($student->attendances()->where('type', AttendanceType::CheckOut)->count())->toBe(1);
});

test('dry run reports without writing', function () {
    $student = makeStudent();
    checkInOnMonday($student);

    $this->travelTo($this->monday->copy()->setTime(19, 0));

    $this->artisan('attendance:auto-checkout', ['--dry-run' => true])->assertSuccessful();

    expect($student->attendances()->where('type', AttendanceType::CheckOut)->count())->toBe(0);
});
