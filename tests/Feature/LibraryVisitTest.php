<?php

use App\Enums\SchoolFeature;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\LibraryVisit;
use App\Models\School;
use App\Models\Student;
use App\Services\Attendance\LibraryVisitRecorder;
use App\Support\SchoolTime;
use Carbon\Carbon;

beforeEach(function () {
    // Dihitung sebelum travelTo supaya tetap menunjuk Senin yang sama.
    $this->monday = SchoolTime::now()->startOfWeek(Carbon::MONDAY)->addWeek()->startOfDay();
    $this->travelTo($this->monday->copy()->setTime(9, 0));

    $this->recorder = app(LibraryVisitRecorder::class);
});

function makeLibrarySchool(bool $enabled = true): array
{
    $school = School::factory()->create([
        'settings' => [SchoolFeature::KunjunganPerpustakaan->settingKey() => $enabled],
    ]);

    $student = Student::factory()->create(['school_id' => $school->id]);

    foreach (range(1, 5) as $day) {
        AttendanceSchedule::factory()->create([
            'school_id' => $school->id,
            'classroom_id' => null,
            'day_of_week' => $day,
            'is_active' => true,
        ]);
    }

    return [$school, $student];
}

function libraryAt(int $hour, int $minute = 0): Carbon
{
    return test()->monday->copy()->setTime($hour, $minute);
}

test('the first tap opens a visit and the next one closes it', function () {
    [, $student] = makeLibrarySchool();

    $masuk = $this->recorder->record($student, timestamp: libraryAt(9, 0));

    expect($masuk['success'])->toBeTrue()
        ->and($masuk['action'])->toBe(LibraryVisitRecorder::ACTION_ENTER)
        ->and($masuk['visit']->exited_at)->toBeNull();

    $keluar = $this->recorder->record($student, timestamp: libraryAt(9, 45));

    expect($keluar['success'])->toBeTrue()
        ->and($keluar['action'])->toBe(LibraryVisitRecorder::ACTION_EXIT)
        ->and($keluar['visit']->durationMinutes())->toBe(45)
        ->and($keluar['message'])->toContain('45 menit');

    expect(LibraryVisit::where('student_id', $student->id)->count())->toBe(1);
});

test('a student may come and go several times in one day', function () {
    [, $student] = makeLibrarySchool();

    $this->recorder->record($student, timestamp: libraryAt(9, 0));
    $this->recorder->record($student, timestamp: libraryAt(9, 30));
    $this->recorder->record($student, timestamp: libraryAt(11, 0));
    $this->recorder->record($student, timestamp: libraryAt(11, 20));

    $visits = LibraryVisit::where('student_id', $student->id)->orderBy('entered_at')->get();

    expect($visits)->toHaveCount(2)
        ->and($visits[0]->durationMinutes())->toBe(30)
        ->and($visits[1]->durationMinutes())->toBe(20);
});

test('a tap within the cooldown is ignored so a nudged card does not close the visit', function () {
    [, $student] = makeLibrarySchool();

    $this->recorder->record($student, timestamp: libraryAt(9, 0));

    $ulang = $this->recorder->record($student, timestamp: libraryAt(9, 0)->addSeconds(5));

    expect($ulang['success'])->toBeFalse()
        ->and($ulang['message'])->toContain('Baru saja tercatat');

    expect(LibraryVisit::where('student_id', $student->id)->first()->exited_at)->toBeNull();
});

test('a visit left open yesterday does not swallow todays first tap', function () {
    [, $student] = makeLibrarySchool();

    LibraryVisit::create([
        'school_id' => $student->school_id,
        'student_id' => $student->id,
        'visit_date' => $this->monday->copy()->subDay()->toDateString(),
        'entered_at' => $this->monday->copy()->subDay()->setTime(10, 0),
    ]);

    $hasil = $this->recorder->record($student, timestamp: libraryAt(9, 0));

    expect($hasil['action'])->toBe(LibraryVisitRecorder::ACTION_ENTER);
});

test('nothing is written to the school attendance table', function () {
    [, $student] = makeLibrarySchool();

    $this->recorder->record($student, timestamp: libraryAt(9, 0));

    expect(Attendance::where('student_id', $student->id)->count())->toBe(0);
});

test('the visit is refused while the feature is off', function () {
    [, $student] = makeLibrarySchool(enabled: false);

    $hasil = $this->recorder->record($student, timestamp: libraryAt(9, 0));

    expect($hasil['success'])->toBeFalse()
        ->and($hasil['message'])->toContain('belum diaktifkan');
});

test('a day without an active schedule is refused', function () {
    [$school, $student] = makeLibrarySchool();
    AttendanceSchedule::where('school_id', $school->id)->update(['is_active' => false]);

    $hasil = $this->recorder->record($student, timestamp: libraryAt(9, 0));

    expect($hasil['success'])->toBeFalse()
        ->and($hasil['message'])->toContain('jadwal');
});

test('the scan page loads without auth and announces a disabled feature', function () {
    [$school] = makeLibrarySchool(enabled: false);

    $this->get(route('public.library-scanner', $school->scanner_token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('scan/library')
            ->where('featureEnabled', false)
            ->where('school.name', $school->name)
        );
});

test('scanning through the endpoint records entry then exit', function () {
    [$school, $student] = makeLibrarySchool();

    $this->postJson(route('public.library-scanner.scan', $school->scanner_token), ['token' => $student->qr_token])
        ->assertOk()
        ->assertJson(['success' => true, 'student' => ['type' => 'LIBRARY', 'type_label' => 'Masuk Perpustakaan']]);

    $this->travelTo(libraryAt(10, 0));

    $this->postJson(route('public.library-scanner.scan', $school->scanner_token), ['token' => $student->qr_token])
        ->assertOk()
        ->assertJson(['success' => true, 'student' => ['type_label' => 'Keluar Perpustakaan', 'status' => '1 jam']]);
});

test('a registered rfid card also works at the library', function () {
    [$school, $student] = makeLibrarySchool();
    $school->setSetting(SchoolFeature::AbsensiRfid->settingKey(), true);
    $student->update(['rfid_uid' => 'A1B2C3D4']);

    $this->postJson(route('public.library-scanner.scan', $school->scanner_token), ['token' => 'a1:b2:c3:d4'])
        ->assertOk()
        ->assertJson(['success' => true, 'student' => ['type' => 'LIBRARY']]);
});

test('an unknown card is refused and a student of another school never matches', function () {
    [$school] = makeLibrarySchool();
    $lain = Student::factory()->create(['school_id' => School::factory()->create()->id]);

    $this->postJson(route('public.library-scanner.scan', $school->scanner_token), ['token' => 'ngawur'])
        ->assertNotFound();

    $this->postJson(route('public.library-scanner.scan', $school->scanner_token), ['token' => $lain->qr_token])
        ->assertNotFound();
});

test('an inactive school refuses the scan', function () {
    [$school, $student] = makeLibrarySchool();
    $school->update(['is_active' => false]);

    $this->postJson(route('public.library-scanner.scan', $school->scanner_token), ['token' => $student->qr_token])
        ->assertStatus(403);
});

test('the admin page lists visits and counts who is still inside', function () {
    $admin = createAdminUser();
    $admin->school->setSetting(SchoolFeature::KunjunganPerpustakaan->settingKey(), true);

    $student = Student::factory()->create(['school_id' => $admin->school_id]);

    LibraryVisit::factory()->create([
        'school_id' => $admin->school_id,
        'student_id' => $student->id,
        'visit_date' => SchoolTime::now()->toDateString(),
        'entered_at' => SchoolTime::now()->setTime(9, 0),
        'exited_at' => SchoolTime::now()->setTime(9, 30),
    ]);

    LibraryVisit::factory()->stillInside()->create([
        'school_id' => $admin->school_id,
        'student_id' => $student->id,
        'visit_date' => SchoolTime::now()->toDateString(),
        'entered_at' => SchoolTime::now()->setTime(10, 0),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.kunjungan-perpus'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/kunjungan-perpus/index')
            ->has('visits.data', 2)
            ->where('summary.visits', 2)
            ->where('summary.students', 1)
            ->where('summary.inside', 1)
        );
});

test('the admin page is closed while the feature is off', function () {
    $admin = createAdminUser();
    $admin->school->setSetting(SchoolFeature::KunjunganPerpustakaan->settingKey(), false);

    $this->actingAs($admin)
        ->get(route('admin.kunjungan-perpus'))
        ->assertForbidden();
});

test('a visit of another school never leaks into the list', function () {
    $admin = createAdminUser();
    $admin->school->setSetting(SchoolFeature::KunjunganPerpustakaan->settingKey(), true);

    $lain = School::factory()->create();
    LibraryVisit::factory()->create([
        'school_id' => $lain->id,
        'student_id' => Student::factory()->create(['school_id' => $lain->id])->id,
        'visit_date' => SchoolTime::now()->toDateString(),
        'entered_at' => SchoolTime::now()->setTime(9, 0),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.kunjungan-perpus'))
        ->assertInertia(fn ($page) => $page->has('visits.data', 0));
});
