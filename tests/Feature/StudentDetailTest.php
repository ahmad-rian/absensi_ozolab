<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Enums\Religion;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\CardGenerationLog;
use App\Models\PrayerAttendance;
use App\Models\School;
use App\Models\SchoolCardLayout;
use App\Models\Student;
use App\Services\Student\StudentStatsBuilder;
use App\Support\SchoolTime;
use Carbon\Carbon;

beforeEach(function () {
    $this->admin = createAdminUser();
    $this->schoolId = $this->admin->school_id;

    $this->student = Student::factory()->create([
        'school_id' => $this->schoolId,
        'religion' => Religion::Islam,
    ]);

    $this->day = SchoolTime::now()->startOfMonth()->addDays(2);
});

function makeCardLog(string $schoolId, Student $student, string $type, string $name, ?string $driveUrl, ?Carbon $at = null): CardGenerationLog
{
    $layout = SchoolCardLayout::create([
        'school_id' => $schoolId,
        'name' => $name,
        'type' => $type,
        'layout_config' => [],
    ]);

    $log = CardGenerationLog::create([
        'school_id' => $schoolId,
        'student_id' => $student->id,
        'school_card_layout_id' => $layout->id,
        'type' => 'card',
        'status' => 'completed',
        'drive_url' => $driveUrl,
    ]);

    if ($at) {
        $log->forceFill(['created_at' => $at])->saveQuietly();
    }

    return $log;
}

test('the detail page renders with the new tabs payload', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.siswa.show', $this->student))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/siswa/show')
            ->has('cards')
            ->has('filters.start')
            ->has('filters.end')
        );
});

test('generated cards expose a direct drive link per card type', function () {
    makeCardLog($this->schoolId, $this->student, 'osis', 'Kartu OSIS', 'https://drive.google.com/file/d/osis/view', SchoolTime::now()->subDay());
    makeCardLog($this->schoolId, $this->student, 'perpustakaan', 'Kartu Perpustakaan', 'https://drive.google.com/file/d/perpus/view', SchoolTime::now());

    $this->actingAs($this->admin)
        ->get(route('admin.siswa.show', $this->student))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('cards', 2)
            ->where('cards.0.layout_type', 'perpustakaan')
            ->where('cards.0.drive_url', 'https://drive.google.com/file/d/perpus/view')
            ->where('cards.1.layout_type', 'osis')
            ->where('cards.1.drive_url', 'https://drive.google.com/file/d/osis/view')
        );
});

test('only the newest card per type is shown', function () {
    makeCardLog($this->schoolId, $this->student, 'osis', 'Kartu OSIS Lama', 'https://drive.google.com/file/d/lama/view', SchoolTime::now()->subDays(3));
    makeCardLog($this->schoolId, $this->student, 'osis', 'Kartu OSIS Baru', 'https://drive.google.com/file/d/baru/view', SchoolTime::now());

    $this->actingAs($this->admin)
        ->get(route('admin.siswa.show', $this->student))
        ->assertInertia(fn ($page) => $page
            ->has('cards', 1)
            ->where('cards.0.drive_url', 'https://drive.google.com/file/d/baru/view')
        );
});

test('a card belonging to another school never leaks in', function () {
    $otherSchool = School::factory()->create();
    $otherStudent = Student::factory()->create(['school_id' => $otherSchool->id]);
    makeCardLog($otherSchool->id, $otherStudent, 'osis', 'Kartu OSIS', 'https://drive.google.com/file/d/lain/view');

    $this->actingAs($this->admin)
        ->get(route('admin.siswa.show', $this->student))
        ->assertInertia(fn ($page) => $page->has('cards', 0));
});

test('attendance stats summarise the range', function () {
    Attendance::factory()->create([
        'school_id' => $this->schoolId,
        'student_id' => $this->student->id,
        'attendance_date' => $this->day->toDateString(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Hadir,
        'recorded_at' => $this->day->copy()->setTime(7, 0),
    ]);

    Attendance::factory()->create([
        'school_id' => $this->schoolId,
        'student_id' => $this->student->id,
        'attendance_date' => $this->day->copy()->addDay()->toDateString(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Terlambat,
        'recorded_at' => $this->day->copy()->addDay()->setTime(9, 0),
    ]);

    $this->actingAs($this->admin);
    $range = SchoolTime::now();
    $attendance = app(StudentStatsBuilder::class)->attendanceFor(
        $this->student,
        $range->copy()->startOfMonth()->toDateString(),
        $range->copy()->endOfMonth()->toDateString(),
    );

    expect($attendance['summary']['hadir'])->toBe(1)
        ->and($attendance['summary']['terlambat'])->toBe(1)
        ->and($attendance['summary']['effective_days'])->toBe(2)
        ->and($attendance['recent'])->toHaveCount(2);
});

test('prayer stats reflect the schools toggle', function () {
    $this->admin->school->setSetting('prayer_enabled', true);

    AttendanceSchedule::factory()->create([
        'school_id' => $this->schoolId,
        'classroom_id' => null,
        'day_of_week' => 1,
    ]);

    Attendance::factory()->create([
        'school_id' => $this->schoolId,
        'student_id' => $this->student->id,
        'attendance_date' => $this->day->toDateString(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Hadir,
        'recorded_at' => $this->day->copy()->setTime(7, 0),
    ]);

    PrayerAttendance::factory()->create([
        'school_id' => $this->schoolId,
        'student_id' => $this->student->id,
        'prayer_date' => $this->day->toDateString(),
        'recorded_at' => $this->day->copy()->setTime(11, 30),
    ]);

    $this->actingAs($this->admin);
    $range = SchoolTime::now();
    $prayer = app(StudentStatsBuilder::class)->prayerFor(
        $this->student->fresh(),
        $range->copy()->startOfMonth()->toDateString(),
        $range->copy()->endOfMonth()->toDateString(),
    );

    expect($prayer['enabled'])->toBeTrue()
        ->and($prayer['covered'])->toBeTrue()
        ->and($prayer['summary']['hadir'])->toBe(1)
        ->and($prayer['summary']['effective_days'])->toBe(1)
        ->and($prayer['window'])->toBe('11:00 – 13:00');
});

test('a non-muslim student is flagged as not covered', function () {
    $this->admin->school->setSetting('prayer_enabled', true);

    $kristen = Student::factory()->create([
        'school_id' => $this->schoolId,
        'religion' => Religion::Kristen,
    ]);

    $this->actingAs($this->admin);
    $range = SchoolTime::now();
    $prayer = app(StudentStatsBuilder::class)->prayerFor(
        $kristen->fresh(),
        $range->copy()->startOfMonth()->toDateString(),
        $range->copy()->endOfMonth()->toDateString(),
    );

    expect($prayer['enabled'])->toBeTrue()
        ->and($prayer['covered'])->toBeFalse();
});

test('a student from another school is not reachable', function () {
    $other = Student::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.siswa.show', $other))
        ->assertNotFound();
});
