<?php

use App\Enums\AttendanceType;
use App\Enums\SchoolFeature;
use App\Models\AttendanceSchedule;
use App\Models\School;
use App\Models\Student;
use App\Support\SchoolTime;
use Carbon\Carbon;

beforeEach(function () {
    $this->monday = SchoolTime::now()->startOfWeek(Carbon::MONDAY)->addWeek()->startOfDay();
    $this->travelTo($this->monday->copy()->setTime(7, 0));

    $this->admin = createAdminUser();
    $this->schoolId = $this->admin->school_id;
    $this->admin->school->setSetting(SchoolFeature::AbsensiRfid->settingKey(), true);

    $this->student = Student::factory()->create(['school_id' => $this->schoolId]);
});

test('the rfid page lists students and their cards', function () {
    $this->student->update(['rfid_uid' => 'A1B2C3D4', 'rfid_registered_at' => SchoolTime::now()]);

    $this->actingAs($this->admin)
        ->get(route('admin.rfid-cards'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/rfid-cards/index')
            ->where('students.data.0.rfid_uid', 'A1B2C3D4')
            ->where('summary.registered', 1)
        );
});

test('the menu is unreachable while the feature is off', function () {
    $this->admin->school->setSetting(SchoolFeature::AbsensiRfid->settingKey(), false);

    $this->actingAs($this->admin)
        ->get(route('admin.rfid-cards'))
        ->assertForbidden();
});

test('a card uid is stored uppercase without separators', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.rfid-cards.store', $this->student), ['rfid_uid' => ' a1:b2:c3:d4 '])
        ->assertRedirect();

    $fresh = $this->student->fresh();

    expect($fresh->rfid_uid)->toBe('A1B2C3D4')
        ->and($fresh->rfid_registered_at)->not->toBeNull();
});

test('one card cannot be registered to two students', function () {
    $other = Student::factory()->create(['school_id' => $this->schoolId, 'rfid_uid' => 'A1B2C3D4']);

    $this->actingAs($this->admin)
        ->post(route('admin.rfid-cards.store', $this->student), ['rfid_uid' => 'a1b2c3d4'])
        ->assertSessionHasErrors('rfid_uid');

    expect($this->student->fresh()->rfid_uid)->toBeNull()
        ->and($other->fresh()->rfid_uid)->toBe('A1B2C3D4');
});

test('a card held by another school is rejected without naming its owner', function () {
    $otherSchool = School::factory()->create();
    Student::factory()->create([
        'school_id' => $otherSchool->id,
        'full_name' => 'Siswa Sekolah Lain',
        'rfid_uid' => 'FFEEDDCC',
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.rfid-cards.store', $this->student), ['rfid_uid' => 'FFEEDDCC'])
        ->assertSessionHasErrors('rfid_uid');

    expect(session('errors')->first('rfid_uid'))
        ->toBe('Kartu ini sudah terdaftar di sekolah lain.')
        ->not->toContain('Siswa Sekolah Lain');
});

test('replacing a card on the same student is allowed', function () {
    $this->student->update(['rfid_uid' => 'AAAA1111']);

    $this->actingAs($this->admin)
        ->post(route('admin.rfid-cards.store', $this->student), ['rfid_uid' => 'BBBB2222'])
        ->assertRedirect();

    expect($this->student->fresh()->rfid_uid)->toBe('BBBB2222');
});

test('a card can be released', function () {
    $this->student->update(['rfid_uid' => 'A1B2C3D4', 'rfid_registered_at' => SchoolTime::now()]);

    $this->actingAs($this->admin)
        ->delete(route('admin.rfid-cards.destroy', $this->student))
        ->assertRedirect();

    $fresh = $this->student->fresh();

    expect($fresh->rfid_uid)->toBeNull()
        ->and($fresh->rfid_registered_at)->toBeNull();
});

test('a student from another school cannot be given a card', function () {
    $other = Student::factory()->create(['school_id' => School::factory()->create()->id]);

    $this->actingAs($this->admin)
        ->post(route('admin.rfid-cards.store', $other), ['rfid_uid' => 'A1B2C3D4'])
        ->assertNotFound();
});

test('tapping a registered card records attendance', function () {
    $school = $this->admin->school;
    $school->setSetting(SchoolFeature::AbsensiRfid->settingKey(), true);
    $this->student->update(['rfid_uid' => 'A1B2C3D4']);

    foreach (range(1, 5) as $day) {
        AttendanceSchedule::factory()->create([
            'school_id' => $school->id,
            'classroom_id' => null,
            'day_of_week' => $day,
            'is_active' => true,
        ]);
    }

    $this->postJson(route('public.scanner.scan', $school->scanner_token), ['token' => 'a1:b2:c3:d4'])
        ->assertOk()
        ->assertJson(['success' => true, 'student' => ['type' => AttendanceType::CheckIn->value]]);

    expect($this->student->attendances()->count())->toBe(1);
});

test('a card is ignored at the gate while the rfid feature is off', function () {
    $school = $this->admin->school;
    $school->setSetting(SchoolFeature::AbsensiRfid->settingKey(), false);
    $this->student->update(['rfid_uid' => 'A1B2C3D4']);

    $this->postJson(route('public.scanner.scan', $school->scanner_token), ['token' => 'A1B2C3D4'])
        ->assertNotFound()
        ->assertJson(['success' => false]);

    expect($this->student->attendances()->count())->toBe(0);
});

test('a card belonging to another school never opens this gate', function () {
    $school = $this->admin->school;
    $school->setSetting(SchoolFeature::AbsensiRfid->settingKey(), true);

    $otherStudent = Student::factory()->create([
        'school_id' => School::factory()->create()->id,
        'rfid_uid' => 'A1B2C3D4',
    ]);

    $this->postJson(route('public.scanner.scan', $school->scanner_token), ['token' => 'A1B2C3D4'])
        ->assertNotFound();

    expect($otherStudent->attendances()->count())->toBe(0);
});
