<?php

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentClassHistory;

test('guests are redirected from kelas index', function () {
    $this->get(route('kelas.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit kelas index', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->get(route('kelas.index'))
        ->assertOk();
});

test('kelas index returns classrooms with relations', function () {
    $user = createAdminUser();
    $classroom = Classroom::factory()->create(['school_id' => $user->school_id]);

    $this->actingAs($user)
        ->get(route('kelas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/kelas/index')
            ->has('classrooms', 1)
            ->has('academic_years')
        );
});

test('authenticated users can bulk create classrooms', function () {
    $user = createAdminUser();
    $academicYear = AcademicYear::factory()->create(['school_id' => $user->school_id]);

    $this->actingAs($user)
        ->post(route('kelas.store'), [
            'grade_level' => 7,
            'parallel_from' => 'A',
            'parallel_to' => 'C',
            'academic_year_id' => $academicYear->id,
            'capacity' => 30,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('classrooms', ['name' => '7A', 'grade_level' => 7, 'capacity' => 30]);
    $this->assertDatabaseHas('classrooms', ['name' => '7B', 'grade_level' => 7, 'capacity' => 30]);
    $this->assertDatabaseHas('classrooms', ['name' => '7C', 'grade_level' => 7, 'capacity' => 30]);
});

test('authenticated users can create a single classroom', function () {
    $user = createAdminUser();
    $academicYear = AcademicYear::factory()->create(['school_id' => $user->school_id]);

    $this->actingAs($user)
        ->post(route('kelas.store'), [
            'grade_level' => 8,
            'parallel_from' => 'A',
            'parallel_to' => 'A',
            'academic_year_id' => $academicYear->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('classrooms', ['name' => '8A', 'grade_level' => 8]);
});

test('store validation requires parallel fields', function () {
    $user = createAdminUser();
    $academicYear = AcademicYear::factory()->create(['school_id' => $user->school_id]);

    $this->actingAs($user)
        ->post(route('kelas.store'), [
            'grade_level' => 7,
            'academic_year_id' => $academicYear->id,
        ])
        ->assertSessionHasErrors(['parallel_from', 'parallel_to']);
});

test('store skips duplicate classrooms', function () {
    $user = createAdminUser();
    $academicYear = AcademicYear::factory()->create(['school_id' => $user->school_id]);
    Classroom::factory()->create([
        'school_id' => $user->school_id,
        'name' => '7A',
        'grade_level' => 7,
        'academic_year_id' => $academicYear->id,
    ]);

    $this->actingAs($user)
        ->post(route('kelas.store'), [
            'grade_level' => 7,
            'parallel_from' => 'A',
            'parallel_to' => 'B',
            'academic_year_id' => $academicYear->id,
        ])
        ->assertRedirect();

    expect(Classroom::where('name', '7A')->count())->toBe(1);
    $this->assertDatabaseHas('classrooms', ['name' => '7B']);
});

test('authenticated users can update a classroom', function () {
    $user = createAdminUser();
    $classroom = Classroom::factory()->create(['name' => 'VII-A', 'school_id' => $user->school_id]);

    $this->actingAs($user)
        ->put(route('kelas.update', $classroom), [
            'name' => 'VII-B',
            'grade_level' => $classroom->grade_level,
            'academic_year_id' => $classroom->academic_year_id,
            'capacity' => 35,
        ])
        ->assertRedirect();

    $classroom->refresh();
    expect($classroom->name)->toBe('VII-B');
    expect($classroom->capacity)->toBe(35);
});

test('authenticated users can delete a classroom without students', function () {
    $user = createAdminUser();
    $classroom = Classroom::factory()->create(['school_id' => $user->school_id]);

    $this->actingAs($user)
        ->delete(route('kelas.destroy', $classroom))
        ->assertRedirect();

    $this->assertDatabaseMissing('classrooms', ['id' => $classroom->id]);
});

test('classroom with students cannot be deleted', function () {
    $user = createAdminUser();
    $classroom = Classroom::factory()->create(['school_id' => $user->school_id]);
    Student::factory()->create(['school_id' => $user->school_id, 'classroom_id' => $classroom->id]);

    $this->actingAs($user)
        ->delete(route('kelas.destroy', $classroom))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('classrooms', ['id' => $classroom->id]);
});

test('mengganti nama kelas menjadi nama yang sudah ada di tahun itu ditolak dengan pesan', function () {
    $user = createAdminUser();
    $tahun = AcademicYear::factory()->create(['school_id' => $user->school_id]);
    Classroom::factory()->create(['school_id' => $user->school_id, 'academic_year_id' => $tahun->id, 'name' => '7A', 'grade_level' => 7]);
    $kelas = Classroom::factory()->create(['school_id' => $user->school_id, 'academic_year_id' => $tahun->id, 'name' => '7B', 'grade_level' => 7]);

    $this->actingAs($user)
        ->put(route('kelas.update', $kelas), ['name' => '7A', 'grade_level' => 7, 'academic_year_id' => $tahun->id])
        ->assertSessionHasErrors('name');

    expect($kelas->fresh()->name)->toBe('7B');
});

test('kelas yang sudah berisi siswa tidak bisa dipindah ke tahun ajaran lain', function () {
    $user = createAdminUser();
    $lama = AcademicYear::factory()->create(['school_id' => $user->school_id, 'name' => '2025/2026']);
    $baru = AcademicYear::factory()->create(['school_id' => $user->school_id, 'name' => '2026/2027']);
    $kelas = Classroom::factory()->create(['school_id' => $user->school_id, 'academic_year_id' => $lama->id, 'name' => '7A', 'grade_level' => 7]);
    Student::factory()->create(['school_id' => $user->school_id, 'classroom_id' => $kelas->id]);

    $this->actingAs($user)
        ->put(route('kelas.update', $kelas), ['name' => '7A', 'grade_level' => 7, 'academic_year_id' => $baru->id])
        ->assertSessionHasErrors('academic_year_id');

    expect($kelas->fresh()->academic_year_id)->toBe($lama->id);
});

test('kelas lama yang punya riwayat siswa tidak bisa dihapus', function () {
    $user = createAdminUser();
    $lama = AcademicYear::factory()->create(['school_id' => $user->school_id, 'name' => '2025/2026']);
    $baru = AcademicYear::factory()->create(['school_id' => $user->school_id, 'name' => '2026/2027', 'is_active' => true]);
    $kelasLama = Classroom::factory()->create(['school_id' => $user->school_id, 'academic_year_id' => $lama->id, 'name' => '7A', 'grade_level' => 7]);
    $kelasBaru = Classroom::factory()->create(['school_id' => $user->school_id, 'academic_year_id' => $baru->id, 'name' => '8A', 'grade_level' => 8]);
    $siswa = Student::factory()->create(['school_id' => $user->school_id, 'classroom_id' => $kelasBaru->id]);
    StudentClassHistory::withoutGlobalScopes()->create([
        'school_id' => $user->school_id, 'student_id' => $siswa->id, 'classroom_id' => $kelasLama->id,
        'academic_year_id' => $lama->id, 'is_current' => false,
    ]);

    $this->actingAs($user)->delete(route('kelas.destroy', $kelasLama))->assertSessionHas('error');

    expect(Classroom::withoutGlobalScopes()->whereKey($kelasLama->id)->exists())->toBeTrue();
});
