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
    // Nama siswa disimpan huruf besar, jadi nama foldernya ikut.
    expect(GoogleDriveService::studentFolderName($student))->toBe('12345 - AHMAD RIAN');
});

/**
 * Query Drive `name = '...'` membedakan huruf besar dan kecil. Folder siswa yang
 * dibuat sebelum nama diseragamkan masih bernama campur, jadi tanpa pencocokan
 * ini setiap siswa lama akan mendapat folder kedua dan foto lamanya hilang dari
 * pandangan aplikasi.
 */
test('folder lama yang kapitalisasinya campur tetap ditemukan', function () {
    $folders = [
        ['id' => 'lain', 'name' => '99999 - Siswa Lain'],
        ['id' => 'cocok', 'name' => '12345 - Ahmad Rian'],
    ];

    expect(GoogleDriveService::pickFolderIgnoringCase($folders, '12345 - AHMAD RIAN'))->toBe('cocok');
});

test('folder yang memang belum ada tetap dilaporkan tidak ada', function () {
    $folders = [
        ['id' => 'lain', 'name' => '99999 - Siswa Lain'],
    ];

    expect(GoogleDriveService::pickFolderIgnoringCase($folders, '12345 - AHMAD RIAN'))->toBeNull();
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
    expect(GoogleDriveService::studentFolderName($student))->toBe($student->id.' - SISWA BARU');
});
