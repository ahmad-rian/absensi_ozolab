<?php

use App\Models\SchoolDriveConfig;
use App\Models\Student;
use App\Services\GoogleDriveService;
use Google\Service\Drive as GoogleDrive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\FileList;
use Google\Service\Drive\Resource\Files as DriveFiles;
use Illuminate\Support\Facades\Cache;

/**
 * Generate massal menjalankan job kartu depan, kartu belakang, dan foto milik
 * siswa yang sama BERSAMAAN di beberapa worker. Tanpa penjagaan, masing-masing
 * melihat "folder belum ada" dan membuat folder sendiri: dua folder bernama sama
 * dengan isi terbelah (laporan Okt 2026).
 */
function driveBalapan(DriveFiles $files): GoogleDriveService
{
    $drive = Mockery::mock(GoogleDrive::class);
    $drive->files = $files;
    $service = (new ReflectionClass(GoogleDriveService::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty(GoogleDriveService::class, 'drive'))->setValue($service, $drive);
    (new ReflectionProperty(GoogleDriveService::class, 'config'))->setValue($service, new SchoolDriveConfig(['root_folder_id' => 'root']));

    return $service;
}

beforeEach(function () {
    Cache::flush();
    config(['services.google.drive_owner_email' => null]);
});

test('job kedua memakai folder yang baru disimpan job pertama walau modelnya basi', function () {
    $siswa = Student::factory()->create(['drive_folder_id' => null]);
    // Dimuat sebelum job pertama menyimpan foldernya — persis keadaan job kedua.
    $basi = Student::withoutGlobalScopes()->find($siswa->id);
    Student::withoutGlobalScopes()->whereKey($siswa->id)->update(['drive_folder_id' => 'folder-pertama']);

    $files = Mockery::mock(DriveFiles::class);
    $files->shouldReceive('get')->with('folder-pertama', Mockery::any())->andReturn(new DriveFile(['id' => 'folder-pertama', 'name' => 'x']));
    $files->shouldNotReceive('create');
    $files->shouldNotReceive('listFiles');

    expect(driveBalapan($files)->resolveStudentFolder($basi))->toBe('folder-pertama');
});

test('folder yang baru dibuat dipakai bersama walau daftar Drive belum memuatnya', function () {
    $pertama = Mockery::mock(DriveFiles::class);
    $pertama->shouldReceive('listFiles')->andReturn(new FileList(['files' => []]));
    $pertama->shouldReceive('create')->once()->andReturn(new DriveFile(['id' => 'kelas-7a']));

    // Pekerja kedua: objek layanan baru, Drive masih melaporkan kosong.
    $kedua = Mockery::mock(DriveFiles::class);
    $kedua->shouldReceive('listFiles')->andReturn(new FileList(['files' => []]));
    $kedua->shouldReceive('get')->with('kelas-7a', Mockery::any())->andReturn(new DriveFile(['id' => 'kelas-7a', 'name' => 'VII A']));
    $kedua->shouldNotReceive('create');

    expect(driveBalapan($pertama)->findOrCreateFolder('VII A', 'root-sekolah'))->toBe('kelas-7a')
        ->and(driveBalapan($kedua)->findOrCreateFolder('VII A', 'root-sekolah'))->toBe('kelas-7a');
});
