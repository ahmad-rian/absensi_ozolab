<?php

use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

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
        'no_absen' => '1',
        'nisn' => '0011223344',
        'religion' => 'ISLAM',
        'birth_place' => 'Jakarta',
        'birth_date' => '2013-04-01',
        'address' => 'Jl. Melati No. 1',
        'parent_name' => 'Budi Santoso',
        'parent_phone' => '081234567890',
        'parent_relation' => 'WALI',
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    $student = Student::where('full_name', 'AHMAD FAUZI')->first();
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
        'parent_relation' => 'WALI',
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    $student = Student::where('full_name', 'SITI AMINAH')->first();
    expect($student)->not->toBeNull()
        ->and($student->parent_name)->toBe('BUDI SANTOSO')
        ->and($student->parent_phone)->toBe('812345678')
        ->and($student->religion->value)->toBe('ISLAM')
        ->and($student->birth_place)->toBe('Jakarta');
});

/**
 * NISN dipegang siswa sekolah lain, atau siswa yang sudah dihapus, tidak boleh
 * menghalangi pendaftaran. Aturan global yang dipakai sebelumnya menolak keduanya,
 * dan operator yang mencari di sekolahnya sendiri tidak menemukan apa pun —
 * pesannya benar tapi mustahil ditindaklanjuti.
 */
function registerStudent(School $school, Classroom $classroom, array $overrides = []): TestResponse
{
    return test()->post('/daftar', array_merge([
        'school_id' => $school->id,
        'full_name' => 'Siswa Uji',
        'no_absen' => '7',
        'nisn' => '0148651992',
        'gender' => 'LAKI_LAKI',
        'religion' => 'ISLAM',
        'classroom_id' => $classroom->id,
        'birth_place' => 'Purwokerto',
        'birth_date' => '2012-01-01',
        'address' => 'Jl. Kenanga No. 2',
        'parent_name' => 'Bapak Uji',
        'parent_phone' => '081234567890',
        'parent_relation' => 'AYAH',
    ], $overrides));
}

test('a nisn held by another school does not block registration', function () {
    $lain = School::factory()->create(['is_active' => true]);
    Student::factory()->create([
        'school_id' => $lain->id,
        'classroom_id' => Classroom::factory()->create(['school_id' => $lain->id])->id,
        'nisn' => '0148651992',
        'nis' => '20250001',
    ]);

    $school = School::factory()->create(['is_active' => true]);
    $classroom = Classroom::factory()->create(['school_id' => $school->id]);

    registerStudent($school, $classroom, ['nis' => '20250001'])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(Student::where('school_id', $school->id)->where('nisn', '0148651992')->exists())->toBeTrue();
});

test('a nisn freed by a deleted student can be registered again', function () {
    $school = School::factory()->create(['is_active' => true]);
    $classroom = Classroom::factory()->create(['school_id' => $school->id]);

    Student::factory()->create([
        'school_id' => $school->id,
        'classroom_id' => $classroom->id,
        'nisn' => '0148651992',
    ])->delete();

    registerStudent($school, $classroom)
        ->assertOk()
        ->assertJson(['success' => true]);
});

test('a nisn already used in the same school is still rejected', function () {
    $school = School::factory()->create(['is_active' => true]);
    $classroom = Classroom::factory()->create(['school_id' => $school->id]);

    Student::factory()->create([
        'school_id' => $school->id,
        'classroom_id' => $classroom->id,
        'nisn' => '0148651992',
    ]);

    registerStudent($school, $classroom)->assertSessionHasErrors('nisn');
});

test('student registration validates required fields', function () {
    $response = $this->post('/daftar', []);

    $response->assertSessionHasErrors([
        'school_id', 'full_name', 'gender', 'classroom_id',
        'no_absen', 'nisn', 'religion', 'birth_place', 'birth_date', 'address',
        'parent_name', 'parent_phone', 'parent_relation',
    ]);
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
    $token = registrationToken();

    $response = $this->postJson('/daftar/preview-photo', [
        'token' => $token,
        'school_id' => $school->id,
        'filename' => 'test.jpg',
    ]);

    $response->assertOk();
    $response->assertJson(['found' => false]);
});

test('preview photo validates required fields', function () {
    $token = registrationToken();

    $response = $this->postJson('/daftar/preview-photo', ['token' => $token]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['school_id', 'filename']);
});

test('preview photo refuses a request without a registration session', function () {
    $school = School::factory()->create(['is_active' => true]);

    $this->postJson('/daftar/preview-photo', [
        'school_id' => $school->id,
        'filename' => 'test.jpg',
    ])->assertForbidden();

    $this->postJson('/daftar/crop-preview', [
        'school_id' => $school->id,
        'filename' => 'test.jpg',
    ])->assertForbidden();
});

/**
 * Buka halaman /daftar dulu supaya token sesinya terbit, seperti browser asli.
 */
function registrationToken(): string
{
    test()->get('/daftar')->assertOk();

    return session('registration_token');
}
