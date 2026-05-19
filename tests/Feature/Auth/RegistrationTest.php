<?php

use App\Enums\Gender;
use App\Enums\ParentRelation;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ParentProfile;
use App\Models\Student;
use App\Models\User;
use Laravel\Fortify\Features;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());

    // Seed roles
    Role::firstOrCreate(['name' => UserRole::Admin->value]);
    Role::firstOrCreate(['name' => UserRole::Guru->value]);
    Role::firstOrCreate(['name' => UserRole::OrangTua->value]);

    // Create academic year and classroom
    $year = AcademicYear::factory()->active()->create();
    $this->classroom = Classroom::factory()->create([
        'academic_year_id' => $year->id,
        'name' => '7A',
        'grade_level' => 7,
    ]);
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('registration screen includes classrooms and relations', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('auth/register')
        ->has('classrooms')
        ->has('relations')
    );
});

test('new users can register with parent and student data', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Budi Santoso',
        'email' => 'budi@example.com',
        'phone' => '81234567890',
        'relation' => ParentRelation::Ayah->value,
        'password' => 'password',
        'password_confirmation' => 'password',
        'students' => [
            [
                'full_name' => 'Andi Santoso',
                'gender' => Gender::LakiLaki->value,
                'classroom_id' => $this->classroom->id,
                'nis' => '',
                'birth_place' => 'Jakarta',
                'birth_date' => '2012-05-15',
                'address' => 'Jl. Merdeka 123',
            ],
        ],
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    // Verify user was created with OrangTua role
    $user = User::where('email', 'budi@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole(UserRole::OrangTua->value))->toBeTrue();

    // Verify parent profile was created
    $parentProfile = ParentProfile::where('user_id', $user->id)->first();
    expect($parentProfile)->not->toBeNull();
    expect($parentProfile->relation)->toBe(ParentRelation::Ayah);
    expect($parentProfile->whatsapp_number)->toBe('81234567890');

    // Verify student was created
    $student = Student::where('parent_profile_id', $parentProfile->id)->first();
    expect($student)->not->toBeNull();
    expect($student->full_name)->toBe('Andi Santoso');
    expect($student->gender)->toBe(Gender::LakiLaki);
    expect($student->classroom_id)->toBe($this->classroom->id);
    expect($student->qr_token)->not->toBeNull();
});

test('users can register multiple students', function () {
    $classroom2 = Classroom::factory()->create([
        'academic_year_id' => $this->classroom->academic_year_id,
        'name' => '8A',
        'grade_level' => 8,
    ]);

    $response = $this->post(route('register.store'), [
        'name' => 'Siti Rahayu',
        'email' => 'siti@example.com',
        'phone' => '81298765432',
        'relation' => ParentRelation::Ibu->value,
        'password' => 'password',
        'password_confirmation' => 'password',
        'students' => [
            [
                'full_name' => 'Putri Rahayu',
                'gender' => Gender::Perempuan->value,
                'classroom_id' => $this->classroom->id,
                'nis' => '',
                'birth_place' => '',
                'birth_date' => '',
                'address' => '',
            ],
            [
                'full_name' => 'Dimas Rahayu',
                'gender' => Gender::LakiLaki->value,
                'classroom_id' => $classroom2->id,
                'nis' => '',
                'birth_place' => '',
                'birth_date' => '',
                'address' => '',
            ],
        ],
    ]);

    $this->assertAuthenticated();

    $user = User::where('email', 'siti@example.com')->first();
    $parentProfile = ParentProfile::where('user_id', $user->id)->first();
    $students = Student::where('parent_profile_id', $parentProfile->id)->get();

    expect($students)->toHaveCount(2);
});

test('registration fails without student data', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '81234567890',
        'relation' => ParentRelation::Ayah->value,
        'password' => 'password',
        'password_confirmation' => 'password',
        'students' => [],
    ]);

    $response->assertSessionHasErrors('students');
    $this->assertGuest();
});

test('registration fails with invalid classroom', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '81234567890',
        'relation' => ParentRelation::Ayah->value,
        'password' => 'password',
        'password_confirmation' => 'password',
        'students' => [
            [
                'full_name' => 'Anak Test',
                'gender' => Gender::LakiLaki->value,
                'classroom_id' => 99999,
                'nis' => '',
                'birth_place' => '',
                'birth_date' => '',
                'address' => '',
            ],
        ],
    ]);

    $response->assertSessionHasErrors('students.0.classroom_id');
    $this->assertGuest();
});

test('registration fails without required parent fields', function () {
    $response = $this->post(route('register.store'), [
        'name' => '',
        'email' => '',
        'phone' => '',
        'relation' => '',
        'password' => 'password',
        'password_confirmation' => 'password',
        'students' => [
            [
                'full_name' => 'Anak Test',
                'gender' => Gender::LakiLaki->value,
                'classroom_id' => $this->classroom->id,
            ],
        ],
    ]);

    $response->assertSessionHasErrors(['name', 'email', 'phone', 'relation']);
    $this->assertGuest();
});
