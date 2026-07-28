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
