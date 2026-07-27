<?php

use App\Enums\Religion;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\PrayerAttendance;
use App\Models\School;
use App\Models\Student;
use App\Support\SchoolTime;
use Carbon\Carbon;

beforeEach(function () {
    $this->monday = SchoolTime::now()->startOfWeek(Carbon::MONDAY)->addWeek()->startOfDay();
    $this->travelTo($this->monday->copy()->setTime(11, 30));
});

function makePrayerSchool(array $settings = []): School
{
    $school = School::factory()->create([
        'settings' => array_merge(['prayer_enabled' => true], $settings),
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

function makeMuslimStudent(School $school): Student
{
    return Student::factory()->create([
        'school_id' => $school->id,
        'religion' => Religion::Islam,
    ]);
}

test('the prayer scan page loads without auth', function () {
    $school = makePrayerSchool();

    $this->get(route('public.prayer-scanner', $school->scanner_token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('scan/prayer')
            ->where('school.name', $school->name)
            ->where('prayer.enabled', true)
            ->where('prayer.start', '11:00')
        );
});

test('an unknown scanner token returns 404', function () {
    $this->get('/scan/token-ngawur/sholat')->assertNotFound();
});

test('a scan records a prayer row and leaves school attendance untouched', function () {
    $school = makePrayerSchool();
    $student = makeMuslimStudent($school);

    $this->postJson(route('public.prayer-scanner.scan', $school->scanner_token), ['token' => $student->qr_token])
        ->assertOk()
        ->assertJson(['success' => true, 'student' => ['type' => 'PRAYER', 'type_label' => 'Sholat Dzuhur']]);

    expect(PrayerAttendance::count())->toBe(1)
        ->and(Attendance::count())->toBe(0);
});

test('a second scan on the same day is rejected', function () {
    $school = makePrayerSchool();
    $student = makeMuslimStudent($school);

    $this->postJson(route('public.prayer-scanner.scan', $school->scanner_token), ['token' => $student->qr_token])
        ->assertOk();

    $this->postJson(route('public.prayer-scanner.scan', $school->scanner_token), ['token' => $student->qr_token])
        ->assertStatus(422)
        ->assertJson(['success' => false]);

    expect(PrayerAttendance::count())->toBe(1);
});

test('scanning works via NIS too', function () {
    $school = makePrayerSchool();
    $student = makeMuslimStudent($school);

    $this->postJson(route('public.prayer-scanner.scan', $school->scanner_token), ['token' => $student->nis])
        ->assertOk()
        ->assertJson(['success' => true]);
});

test('a student from another school is rejected', function () {
    $schoolA = makePrayerSchool();
    $schoolB = makePrayerSchool();
    $studentB = makeMuslimStudent($schoolB);

    $this->postJson(route('public.prayer-scanner.scan', $schoolA->scanner_token), ['token' => $studentB->qr_token])
        ->assertStatus(404);

    expect(PrayerAttendance::count())->toBe(0);
});

test('an inactive school rejects prayer scans', function () {
    $school = makePrayerSchool();
    $student = makeMuslimStudent($school);
    $school->update(['is_active' => false]);

    $this->postJson(route('public.prayer-scanner.scan', $school->scanner_token), ['token' => $student->qr_token])
        ->assertStatus(403);
});

test('a school with the feature off rejects scans and says so on the page', function () {
    $school = makePrayerSchool(['prayer_enabled' => false]);
    $student = makeMuslimStudent($school);

    $this->get(route('public.prayer-scanner', $school->scanner_token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('prayer.enabled', false));

    $this->postJson(route('public.prayer-scanner.scan', $school->scanner_token), ['token' => $student->qr_token])
        ->assertStatus(422)
        ->assertJson(['success' => false]);
});

test('a non-muslim student is rejected unless the school opts in', function () {
    $school = makePrayerSchool();
    $kristen = Student::factory()->create(['school_id' => $school->id, 'religion' => Religion::Kristen]);

    $this->postJson(route('public.prayer-scanner.scan', $school->scanner_token), ['token' => $kristen->qr_token])
        ->assertStatus(422);

    $school->setSetting('prayer_all_religions', true);

    $this->postJson(route('public.prayer-scanner.scan', $school->scanner_token), ['token' => $kristen->qr_token])
        ->assertOk()
        ->assertJson(['success' => true]);
});

test('the admin prayer scanner needs the scanner permission', function () {
    $school = makePrayerSchool();
    $student = makeMuslimStudent($school);
    $admin = createAdminUser(['school_id' => $school->id]);

    $this->actingAs($admin)
        ->get(route('admin.scanner.sholat'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/scanner/prayer')->where('prayer.enabled', true));

    $this->actingAs($admin)
        ->postJson(route('admin.scanner.sholat.scan'), ['token' => $student->qr_token])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(PrayerAttendance::first()->recorded_by)->toBe($admin->id);
});

test('guests cannot use the admin prayer scanner', function () {
    $this->postJson(route('admin.scanner.sholat.scan'), ['token' => 'abc'])->assertUnauthorized();
});
