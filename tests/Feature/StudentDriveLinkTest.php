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

/**
 * Locator yang memakai klien Drive palsu, bukan API Google.
 *
 * Menimpa buildDrive(), bukan driveFor(), supaya memoisasi klien per sekolah
 * ikut dijalani seperti di produksi — dan bisa dihitung lewat `$built`.
 */
function locatorUsing(GoogleDriveService $drive): StudentDrivePhotoLocator
{
    return new class($drive) extends StudentDrivePhotoLocator
    {
        public int $built = 0;

        public function __construct(private GoogleDriveService $fake) {}

        protected function buildDrive(SchoolDriveConfig $config): GoogleDriveService
        {
            $this->built++;

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

test('the filename typed at registration is tried before anything else', function () {
    // Itu nama berkas aslinya. Nama baku `{slug}-{nis}-foto.png` hanya ada kalau
    // siswa ini pernah lewat jalur generate; yang diketik operator selalu ada.
    activeDriveConfig($this->schoolId);
    $this->student->forceFill(['photo_drive_filename' => 'FIC_0008.JPG'])->saveQuietly();

    $drive = mock(GoogleDriveService::class, function (MockInterface $mock) {
        $mock->shouldReceive('findStudentFolderId')->andReturn('folder-siswa');
        $mock->shouldReceive('findFileByName')->once()->with('FIC_0008.JPG', 'folder-siswa')
            ->andReturn([['id' => 'asli', 'name' => 'FIC_0008.JPG']]);
        $mock->shouldNotReceive('imagesInFolder');
    });

    expect(locatorUsing($drive)->inspect($this->student->fresh())['file_id'])->toBe('asli');
});

test('a camera-named photo is found once the system files are set aside', function () {
    // Foto asli memang bernama keluaran kamera, bukan nama siswanya. Semua berkas
    // yang sistem tulis sendiri berawalan `{slug}-{nis}-`, jadi yang tersisa
    // setelah awalan itu dibuang adalah berkas yang ditaruh manusia.
    activeDriveConfig($this->schoolId);

    $prefix = GoogleDriveService::studentFilePrefix($this->student);

    $drive = mock(GoogleDriveService::class, function (MockInterface $mock) use ($prefix) {
        $mock->shouldReceive('findStudentFolderId')->andReturn('folder-siswa');
        $mock->shouldReceive('findFileByName')->andReturn([]);
        $mock->shouldReceive('imagesInFolder')->andReturn([
            ['id' => 'kartu', 'name' => $prefix.'osis.png', 'modifiedTime' => null],
            ['id' => 'lembar', 'name' => $prefix.'4r.png', 'modifiedTime' => null],
            ['id' => 'asli', 'name' => 'FIC_0008.JPG', 'modifiedTime' => null],
        ]);
    });

    expect(locatorUsing($drive)->inspect($this->student->fresh())['file_id'])->toBe('asli');
});

test('two loose images are never guessed between', function () {
    // Dua foto asing berarti dua sesi atau salah taruh. Memilih salah satunya
    // berisiko memasang wajah anak lain di kartunya — persis keluhan yang sedang
    // diperbaiki, jadi lebih baik dilaporkan tidak ketemu.
    activeDriveConfig($this->schoolId);

    $drive = mock(GoogleDriveService::class, function (MockInterface $mock) {
        $mock->shouldReceive('findStudentFolderId')->andReturn('folder-siswa');
        $mock->shouldReceive('findFileByName')->andReturn([]);
        $mock->shouldReceive('imagesInFolder')->andReturn([
            ['id' => 'satu', 'name' => 'FIC_0008.JPG', 'modifiedTime' => '2026-08-01T00:00:00Z'],
            ['id' => 'dua', 'name' => 'FIC_0009.JPG', 'modifiedTime' => '2026-08-02T00:00:00Z'],
        ]);
    });

    expect(locatorUsing($drive)->inspect($this->student->fresh()))->toBeNull();
});

test('one Drive client is built per school, not per student', function () {
    // Konstruktor GoogleDriveService menyegarkan token OAuth lewat HTTP. Satu
    // klien per siswa berarti 1853 penyegaran token untuk satu kali audit, dan
    // folderCache miliknya lahir kosong tiap kali.
    activeDriveConfig($this->schoolId);

    $lain = Student::factory()->create([
        'school_id' => $this->schoolId,
        'classroom_id' => $this->classroom->id,
    ]);

    $drive = mock(GoogleDriveService::class, function (MockInterface $mock) {
        $mock->shouldReceive('findStudentFolderId')->andReturn('folder-siswa');
        $mock->shouldReceive('findFileByName')->andReturn([['id' => 'ketemu', 'name' => 'foto.png']]);
    });

    $locator = locatorUsing($drive);
    $locator->inspect($this->student->fresh());
    $locator->inspect($lain->fresh());

    expect($locator->built)->toBe(1);
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
