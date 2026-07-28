<?php

use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use App\Services\GoogleDriveService;

test('hasil generate siswa memakai folder kelas dan nama siswa', function () {
    $school = School::factory()->create();
    $classroom = Classroom::factory()->create(['school_id' => $school->id, 'name' => 'X TKJ 1']);
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'classroom_id' => $classroom->id,
        'nis' => '12345',
        'full_name' => 'Ahmad Rian',
    ]);

    expect(GoogleDriveService::classFolderName($student))->toBe('X TKJ 1');
    expect(GoogleDriveService::studentFolderName($student))->toBe('12345 - Ahmad Rian');
});

test('foto siswa di Drive memakai pola nama yang sama dengan kartunya', function () {
    $school = School::factory()->create();
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'nis' => '0834314',
        'full_name' => 'Harlan Ferguson',
    ]);

    expect(GoogleDriveService::studentPhotoFileName($student))->toBe('harlan-ferguson-0834314-foto.png');
});

test('nama foto di Drive jatuh ke ULID saat siswa belum punya NIS', function () {
    $school = School::factory()->create();
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'nis' => null,
        'full_name' => "Ana O'Brien",
    ]);

    expect(GoogleDriveService::studentPhotoFileName($student))->toBe('ana-obrien-'.$student->id.'-foto.png');
});

test('siswa tanpa kelas dan tanpa NIS tetap dapat nama folder yang unik', function () {
    $school = School::factory()->create();
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'classroom_id' => null,
        'nis' => null,
        'full_name' => 'Siswa Baru',
    ]);

    expect(GoogleDriveService::classFolderName($student))->toBe('Tanpa Kelas');
    expect(GoogleDriveService::studentFolderName($student))->toBe($student->id.' - Siswa Baru');
});
