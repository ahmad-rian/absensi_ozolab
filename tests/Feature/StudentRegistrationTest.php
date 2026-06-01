<?php

use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('student registration page can be rendered', function () {
    $school = School::factory()->create(['is_active' => true]);
    Classroom::factory()->create(['school_id' => $school->id]);

    $response = $this->get('/daftar');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('student-register')
        ->has('schools', 1)
        ->has('classrooms', 1)
    );
});

test('student registration page only shows active schools', function () {
    School::factory()->create(['is_active' => true]);
    School::factory()->create(['is_active' => false]);

    $response = $this->get('/daftar');

    $response->assertInertia(fn ($page) => $page->has('schools', 1));
});

test('a student can be registered via the form', function () {
    $school = School::factory()->create(['is_active' => true]);
    $classroom = Classroom::factory()->create(['school_id' => $school->id]);

    $response = $this->post('/daftar', [
        'school_id' => $school->id,
        'full_name' => 'Ahmad Fauzi',
        'gender' => 'LAKI_LAKI',
        'classroom_id' => $classroom->id,
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    $student = Student::where('full_name', 'Ahmad Fauzi')->first();
    expect($student)->not->toBeNull()
        ->and($student->school_id)->toBe($school->id)
        ->and($student->classroom_id)->toBe($classroom->id)
        ->and($student->gender->value)->toBe('LAKI_LAKI')
        ->and($student->qr_token)->not->toBeNull()
        ->and($student->is_active)->toBeTrue();
});

test('a student can be registered with all optional fields', function () {
    $school = School::factory()->create(['is_active' => true]);
    $classroom = Classroom::factory()->create(['school_id' => $school->id]);

    $response = $this->post('/daftar', [
        'school_id' => $school->id,
        'full_name' => 'Siti Aminah',
        'nis' => '20250001',
        'no_absen' => '15',
        'nisn' => '0012345678',
        'gender' => 'PEREMPUAN',
        'religion' => 'ISLAM',
        'classroom_id' => $classroom->id,
        'birth_place' => 'Jakarta',
        'birth_date' => '2012-05-15',
        'address' => 'Jl. Merdeka No. 10',
        'parent_name' => 'Budi Santoso',
        'parent_phone' => '812345678',
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    $student = Student::where('full_name', 'Siti Aminah')->first();
    expect($student)->not->toBeNull()
        ->and($student->parent_name)->toBe('Budi Santoso')
        ->and($student->parent_phone)->toBe('812345678')
        ->and($student->religion->value)->toBe('ISLAM')
        ->and($student->birth_place)->toBe('Jakarta');
});

test('student registration validates required fields', function () {
    $response = $this->post('/daftar', []);

    $response->assertSessionHasErrors(['school_id', 'full_name', 'gender', 'classroom_id']);
});

test('student registration validates gender enum', function () {
    $school = School::factory()->create(['is_active' => true]);
    $classroom = Classroom::factory()->create(['school_id' => $school->id]);

    $response = $this->post('/daftar', [
        'school_id' => $school->id,
        'full_name' => 'Test',
        'gender' => 'INVALID',
        'classroom_id' => $classroom->id,
    ]);

    $response->assertSessionHasErrors(['gender']);
});

test('student registration does not require authentication', function () {
    $response = $this->get('/daftar');

    $response->assertStatus(200);
});

test('preview photo returns not found when drive is not configured', function () {
    $school = School::factory()->create(['is_active' => true]);

    $response = $this->postJson('/daftar/preview-photo', [
        'school_id' => $school->id,
        'filename' => 'test.jpg',
    ]);

    $response->assertOk();
    $response->assertJson(['found' => false]);
});

test('preview photo validates required fields', function () {
    $response = $this->postJson('/daftar/preview-photo', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['school_id', 'filename']);
});
