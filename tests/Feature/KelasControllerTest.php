<?php

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;

test('guests are redirected from kelas index', function () {
    $this->get(route('kelas.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit kelas index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('kelas.index'))
        ->assertOk();
});

test('kelas index returns classrooms with relations', function () {
    $user = User::factory()->create();
    $classroom = Classroom::factory()->create();

    $this->actingAs($user)
        ->get(route('kelas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/kelas/index')
            ->has('classrooms', 1)
            ->has('academic_years')
            ->has('teachers')
        );
});

test('authenticated users can create a classroom', function () {
    $user = User::factory()->create();
    $academicYear = AcademicYear::factory()->create();

    $this->actingAs($user)
        ->post(route('kelas.store'), [
            'name' => 'VII-A',
            'grade_level' => 7,
            'academic_year_id' => $academicYear->id,
            'capacity' => 30,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('classrooms', [
        'name' => 'VII-A',
        'grade_level' => 7,
        'capacity' => 30,
    ]);
});

test('store validation requires name', function () {
    $user = User::factory()->create();
    $academicYear = AcademicYear::factory()->create();

    $this->actingAs($user)
        ->post(route('kelas.store'), [
            'grade_level' => 7,
            'academic_year_id' => $academicYear->id,
            'capacity' => 30,
        ])
        ->assertSessionHasErrors('name');
});

test('store validation requires grade_level between 7 and 12', function () {
    $user = User::factory()->create();
    $academicYear = AcademicYear::factory()->create();

    $this->actingAs($user)
        ->post(route('kelas.store'), [
            'name' => 'Test',
            'grade_level' => 6,
            'academic_year_id' => $academicYear->id,
            'capacity' => 30,
        ])
        ->assertSessionHasErrors('grade_level');
});

test('authenticated users can update a classroom', function () {
    $user = User::factory()->create();
    $classroom = Classroom::factory()->create(['name' => 'VII-A']);

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
    $user = User::factory()->create();
    $classroom = Classroom::factory()->create();

    $this->actingAs($user)
        ->delete(route('kelas.destroy', $classroom))
        ->assertRedirect();

    $this->assertDatabaseMissing('classrooms', ['id' => $classroom->id]);
});

test('classroom with students cannot be deleted', function () {
    $user = User::factory()->create();
    $classroom = Classroom::factory()->create();
    Student::factory()->create(['classroom_id' => $classroom->id]);

    $this->actingAs($user)
        ->delete(route('kelas.destroy', $classroom))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('classrooms', ['id' => $classroom->id]);
});
