<?php

use App\Jobs\SyncStudentDriveFolderJob;
use App\Models\Classroom;
use App\Models\SchoolDriveConfig;
use App\Models\Student;
use App\Services\GoogleDriveService;
use App\Services\Student\StudentDrivePhotoLocator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

/*
 | Tautan Drive seorang siswa harus berhenti bergeser.
 |
 | Letak foto siswa selama ini diturunkan ulang setiap kali dari jalur folder
 | `{Sekolah}/{Kelas}/{NIS - Nama}`. Ketiga nilai itu berubah — naik kelas, NIS
 | diperbaiki, nama diseragamkan huruf besar — dan begitu berubah, pencarian
 | menunjuk folder lain. Operator melihatnya sebagai "foto anaknya hilang" dan
 | "URL gdrive ganti sendiri". Id berkas Drive tidak ikut berubah, jadi ia yang
 | disimpan.
 */

beforeEach(function () {
    $this->admin = createAdminUser();
    $this->schoolId = $this->admin->school_id;

    $this->classroom = Classroom::factory()->create(['school_id' => $this->schoolId]);
    $this->student = Student::factory()->create([
        'school_id' => $this->schoolId,
        'classroom_id' => $this->classroom->id,
    ]);
});

/** Locator yang memakai klien Drive palsu, bukan API Google. */
function locatorUsing(GoogleDriveService $drive): StudentDrivePhotoLocator
{
    return new class($drive) extends StudentDrivePhotoLocator
    {
        public function __construct(private GoogleDriveService $fake) {}

        protected function driveFor(SchoolDriveConfig $config): GoogleDriveService
        {
            return $this->fake;
        }
    };
}

function activeDriveConfig(string $schoolId): SchoolDriveConfig
{
    return SchoolDriveConfig::create([
        'school_id' => $schoolId,
        'is_active' => true,
        'root_folder_id' => 'root-sekolah',
        'service_account_json' => '{"type":"service_account"}',
    ]);
}

test('a stored file id is used instead of rebuilding the folder path', function () {
    activeDriveConfig($this->schoolId);
    $this->student->forceFill(['photo_drive_file_id' => 'berkas-tetap'])->saveQuietly();

    $drive = mock(GoogleDriveService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fileById')->once()->with('berkas-tetap')
            ->andReturn(['id' => 'berkas-tetap', 'name' => 'rian-123-foto.png']);
        // Jalur nama folder tidak boleh disentuh sama sekali — itulah jalur yang
        // bergeser setiap kali siswa naik kelas.
        $mock->shouldNotReceive('findStudentFolderId');
        $mock->shouldNotReceive('imagesInFolder');
    });

    $found = locatorUsing($drive)->locate($this->student->fresh());

    expect($found['file_id'])->toBe('berkas-tetap')
        ->and($found['view_url'])->toContain('berkas-tetap');
});

test('a dead file id falls back to searching the folder', function () {
    // Berkas yang dihapus operator tidak boleh membuat siswa itu permanen tanpa
    // foto: id yang mati harus jatuh ke pencarian nama seperti sebelumnya.
    activeDriveConfig($this->schoolId);
    $this->student->forceFill(['photo_drive_file_id' => 'sudah-dihapus'])->saveQuietly();

    $drive = mock(GoogleDriveService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fileById')->with('sudah-dihapus')->andReturnNull();
        $mock->shouldReceive('findStudentFolderId')->once()->andReturn('folder-siswa');
        $mock->shouldReceive('findFileByName')->once()
            ->andReturn([['id' => 'berkas-baru', 'name' => 'rian-123-foto.png']]);
    });

    $found = locatorUsing($drive)->locate($this->student->fresh());

    expect($found['file_id'])->toBe('berkas-baru');
});

test('a photo found by name is remembered so it is never guessed twice', function () {
    activeDriveConfig($this->schoolId);

    $drive = mock(GoogleDriveService::class, function (MockInterface $mock) {
        $mock->shouldReceive('findStudentFolderId')->andReturn('folder-siswa');
        $mock->shouldReceive('findFileByName')->andReturn([['id' => 'ketemu', 'name' => 'foto.png']]);
    });

    locatorUsing($drive)->locate($this->student->fresh());

    expect($this->student->fresh()->photo_drive_file_id)->toBe('ketemu')
        ->and($this->student->fresh()->drive_folder_id)->toBe('folder-siswa');
});

test('inspect looks without writing, so the audit stays read-only', function () {
    activeDriveConfig($this->schoolId);
    Cache::flush();

    $drive = mock(GoogleDriveService::class, function (MockInterface $mock) {
        $mock->shouldReceive('findStudentFolderId')->andReturn('folder-siswa');
        $mock->shouldReceive('findFileByName')->andReturn([['id' => 'ketemu', 'name' => 'foto.png']]);
    });

    $found = locatorUsing($drive)->inspect($this->student->fresh());

    expect($found['file_id'])->toBe('ketemu')
        ->and($this->student->fresh()->photo_drive_file_id)->toBeNull();
});

test('moving a student to another class moves the Drive folder rather than making a new one', function () {
    Queue::fake();
    $this->student->forceFill(['drive_folder_id' => 'folder-siswa'])->saveQuietly();

    $lain = Classroom::factory()->create(['school_id' => $this->schoolId]);
    $this->student->update(['classroom_id' => $lain->id]);

    Queue::assertPushed(SyncStudentDriveFolderJob::class, fn ($job) => $job->studentId === $this->student->id);
});

test('renaming a student or fixing a NIS also moves the folder', function () {
    Queue::fake();
    $this->student->forceFill(['drive_folder_id' => 'folder-siswa'])->saveQuietly();

    $this->student->update(['full_name' => 'Nama Baru']);
    $this->student->update(['nis' => '999123']);

    Queue::assertPushed(SyncStudentDriveFolderJob::class, 2);
});

test('a student with nothing on Drive yet is left alone', function () {
    // Tidak ada folder tersimpan berarti belum pernah ada berkas. Mengantre job
    // untuk setiap siswa yang namanya diperbaiki akan membanjiri antrean kartu.
    Queue::fake();

    $this->student->update(['full_name' => 'Nama Baru']);

    Queue::assertNothingPushed();
});

test('an unrelated edit does not touch Drive', function () {
    Queue::fake();
    $this->student->forceFill(['drive_folder_id' => 'folder-siswa'])->saveQuietly();

    $this->student->update(['no_absen' => '17']);

    Queue::assertNothingPushed();
});
