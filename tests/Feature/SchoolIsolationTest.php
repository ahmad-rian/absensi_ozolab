<?php

use App\Models\AcademicYear;
use App\Models\AttendanceSchedule;
use App\Models\Classroom;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\Student;

/**
 * Admin sekolah A tidak boleh menyentuh data sekolah B, walaupun ULID-nya
 * ditempel langsung ke URL.
 */
beforeEach(function () {
    $this->admin = createAdminUser();

    $this->otherSchool = School::factory()->create();
    $this->otherClassroom = Classroom::factory()->create(['school_id' => $this->otherSchool->id]);
    $this->otherStudent = Student::factory()->create([
        'school_id' => $this->otherSchool->id,
        'classroom_id' => $this->otherClassroom->id,
    ]);
    $this->otherParent = ParentProfile::factory()->create(['school_id' => $this->otherSchool->id]);
    $this->otherSchedule = AttendanceSchedule::factory()->create([
        'school_id' => $this->otherSchool->id,
        'classroom_id' => $this->otherClassroom->id,
    ]);
});

test('another school\'s student is not reachable', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.siswa.show', $this->otherStudent))
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->get(route('admin.siswa.edit', $this->otherStudent))
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->get(route('admin.siswa.qr', $this->otherStudent))
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->delete(route('admin.siswa.destroy', $this->otherStudent))
        ->assertNotFound();

    $this->assertDatabaseHas('students', ['id' => $this->otherStudent->id, 'deleted_at' => null]);
});

test('another school\'s parent profile is not reachable', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.orang-tua.show', $this->otherParent))
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->delete(route('admin.orang-tua.destroy', $this->otherParent))
        ->assertNotFound();
});

test('another school\'s classroom cannot be updated or deleted', function () {
    $this->actingAs($this->admin)
        ->put(route('kelas.update', $this->otherClassroom), [
            'name' => 'DIBAJAK',
            'grade_level' => 7,
            'academic_year_id' => $this->otherClassroom->academic_year_id,
            'capacity' => 30,
        ])
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->delete(route('kelas.destroy', $this->otherClassroom))
        ->assertNotFound();

    expect($this->otherClassroom->fresh()->name)->not->toBe('DIBAJAK');
});

test('another school\'s attendance schedule cannot be updated', function () {
    $this->actingAs($this->admin)
        ->put(route('jadwal-absensi.update', $this->otherSchedule), [
            'day_of_week' => 3,
            'check_in_start' => '05:00',
            'check_in_end' => '06:00',
            'late_threshold' => '06:00',
            'check_out_start' => '11:00',
            'check_out_end' => '12:00',
        ])
        ->assertNotFound();
});

test('listings never leak another school rows', function () {
    Student::factory()->create(['school_id' => $this->admin->school_id]);

    $this->actingAs($this->admin)
        ->get(route('admin.siswa.index'))
        ->assertInertia(fn ($page) => $page->has('students.data', 1));

    $this->actingAs($this->admin)
        ->get(route('kelas.index'))
        ->assertInertia(fn ($page) => $page->has('classrooms', 0));
});

test('a foreign classroom id is rejected when creating a student', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.siswa.store'), [
            'nis' => '99887766',
            'full_name' => 'Siswa Uji',
            'gender' => 'L',
            'classroom_id' => $this->otherClassroom->id,
        ])
        ->assertSessionHasErrors('classroom_id');
});

test('a foreign academic year is rejected when creating a classroom', function () {
    $foreignYear = AcademicYear::factory()->create(['school_id' => $this->otherSchool->id]);

    $this->actingAs($this->admin)
        ->post(route('kelas.store'), [
            'grade_level' => 7,
            'parallel_from' => 'A',
            'parallel_to' => 'A',
            'academic_year_id' => $foreignYear->id,
            'capacity' => 30,
        ])
        ->assertSessionHasErrors('academic_year_id');
});

test('a school admin cannot switch to another school', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.switch-school'), ['school_id' => $this->otherSchool->id])
        ->assertForbidden();

    expect($this->admin->fresh()->school_id)->not->toBe($this->otherSchool->id);
});

test('a stale session school id cannot move a school admin', function () {
    $this->actingAs($this->admin)
        ->withSession(['current_school_id' => $this->otherSchool->id])
        ->get(route('admin.siswa.index'))
        ->assertInertia(fn ($page) => $page->where('currentSchool.id', $this->admin->school_id));
});

test('the school list is only shared with super admins', function () {
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('schools', []));

    $this->actingAs(createSuperAdminUser())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('schools'));
});

test('the student api now requires authentication', function () {
    $this->getJson('/api/students')->assertUnauthorized();
    $this->getJson('/api/schools')->assertUnauthorized();
});

test('the student api only returns the callers school', function () {
    Student::factory()->create(['school_id' => $this->admin->school_id]);

    $response = $this->actingAs($this->admin)->getJson('/api/students');

    $response->assertOk();
    expect($response->json('total'))->toBe(1);

    $this->actingAs($this->admin)
        ->getJson("/api/students/{$this->otherStudent->id}")
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->getJson("/api/schools/{$this->otherSchool->id}/students")
        ->assertNotFound();
});
