<?php

use App\Models\Classroom;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('registration with parent data creates a parent profile', function () {
    $school = School::factory()->create(['is_active' => true]);
    $classroom = Classroom::factory()->create(['school_id' => $school->id]);

    $response = $this->post('/daftar', [
        'school_id' => $school->id,
        'full_name' => 'Anak Pertama',
        'gender' => 'LAKI_LAKI',
        'classroom_id' => $classroom->id,
        'parent_name' => 'Budi Santoso',
        'parent_phone' => '081234567890',
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    $student = Student::where('full_name', 'Anak Pertama')->first();
    expect($student->parent_profile_id)->not->toBeNull();

    $parentProfile = ParentProfile::find($student->parent_profile_id);
    expect($parentProfile)->not->toBeNull()
        ->and($parentProfile->whatsapp_number)->toBe('081234567890')
        ->and($parentProfile->school_id)->toBe($school->id)
        ->and($parentProfile->relation->value)->toBe('WALI');

    expect($parentProfile->user->name)->toBe('Budi Santoso');
});

test('registration with same parent phone reuses existing parent profile', function () {
    $school = School::factory()->create(['is_active' => true]);
    $classroom = Classroom::factory()->create(['school_id' => $school->id]);

    // Register first child
    $this->post('/daftar', [
        'school_id' => $school->id,
        'full_name' => 'Anak Pertama',
        'gender' => 'LAKI_LAKI',
        'classroom_id' => $classroom->id,
        'parent_name' => 'Budi Santoso',
        'parent_phone' => '081234567890',
    ]);

    // Register second child with same parent phone
    $this->post('/daftar', [
        'school_id' => $school->id,
        'full_name' => 'Anak Kedua',
        'gender' => 'PEREMPUAN',
        'classroom_id' => $classroom->id,
        'parent_name' => 'Budi Santoso',
        'parent_phone' => '081234567890',
    ]);

    $students = Student::whereIn('full_name', ['Anak Pertama', 'Anak Kedua'])->get();
    expect($students)->toHaveCount(2);

    // Both should link to the same parent profile
    expect($students[0]->parent_profile_id)->toBe($students[1]->parent_profile_id);

    // Only one parent profile should exist for this phone
    expect(ParentProfile::where('whatsapp_number', '081234567890')->count())->toBe(1);
});

test('registration without parent data does not create parent profile', function () {
    $school = School::factory()->create(['is_active' => true]);
    $classroom = Classroom::factory()->create(['school_id' => $school->id]);

    $this->post('/daftar', [
        'school_id' => $school->id,
        'full_name' => 'Anak Tanpa Ortu',
        'gender' => 'LAKI_LAKI',
        'classroom_id' => $classroom->id,
    ]);

    $student = Student::where('full_name', 'Anak Tanpa Ortu')->first();
    expect($student->parent_profile_id)->toBeNull();
    expect(ParentProfile::count())->toBe(0);
});
