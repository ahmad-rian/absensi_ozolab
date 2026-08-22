<?php

use App\Models\Classroom;
use App\Models\SchoolDriveConfig;
use App\Models\Student;
use App\Services\GoogleDriveService;
use App\Services\Student\StudentDrivePhotoLocator;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;

/*
 | `drive:audit-siswa` dibaca manusia, dan itu bagian dari apa yang harus benar.
 |
 | Jalan pertamanya di server mengeluarkan "1849 dari 1853 siswa perlu diperiksa"
 | — angka yang terbaca seperti bencana padahal mayoritasnya hanya belum
 | di-backfill. Test di sini menjaga tiga hal yang membuat laporan itu bisa
 | ditindak: keadaan dipisah, kelas yang hilang tidak diulang per siswa, dan
 | tanpa --fix tidak ada satu baris pun yang berubah.
 */

beforeEach(function () {
    $this->admin = createAdminUser();
    $this->schoolId = $this->admin->school_id;

    $this->classroom = Classroom::factory()->create([
        'school_id' => $this->schoolId,
        'name' => '7C',
    ]);

    SchoolDriveConfig::create([
        'school_id' => $this->schoolId,
        'is_active' => true,
        'root_folder_id' => 'root-sekolah',
        'service_account_json' => '{"type":"service_account"}',
    ]);

    Cache::flush();
});

/**
 * Siswa yang pernah difoto. Tanpa `photo_path` audit melewatinya — ia memang
 * belum pernah difoto, dan melaporkannya menenggelamkan yang benar-benar rusak.
 */
function auditedStudent(array $attributes = []): Student
{
    return Student::factory()->create(array_merge([
        'school_id' => test()->schoolId,
        'classroom_id' => test()->classroom->id,
        'photo_path' => 'photos/students/ada.png',
    ], $attributes));
}

/** Pasang locator yang memakai klien Drive palsu ke container. */
function auditLocator(GoogleDriveService $drive): StudentDrivePhotoLocator
{
    $locator = new class($drive) extends StudentDrivePhotoLocator
    {
        public function __construct(private GoogleDriveService $fake) {}

        protected function buildDrive(SchoolDriveConfig $config): GoogleDriveService
        {
            return $this->fake;
        }
    };

    app()->instance(StudentDrivePhotoLocator::class, $locator);

    return $locator;
}

test('a missing class folder is reported once, not once per student', function () {
    // Kalau 28 siswa satu kelas gagal berbarengan, yang hilang adalah folder
    // kelasnya — satu folder, bukan 28. Mencetaknya per siswa membuat tabelnya
    // sepanjang layar dan menyembunyikan bahwa perbaikannya cuma satu tindakan.
    $siswa = collect(range(1, 3))->map(fn (int $i) => auditedStudent(['nis' => "900{$i}"]));

    auditLocator(mock(GoogleDriveService::class, function (MockInterface $mock) {
        $mock->shouldReceive('findSchoolRoot')->andReturn('root-sekolah');
        $mock->shouldReceive('findFolder')->with('7C', 'root-sekolah')->andReturnNull();
    }));

    expect($siswa)->toHaveCount(3);

    $this->artisan('drive:audit-siswa')
        ->expectsOutputToContain('Folder kelas yang tidak ada di Drive')
        ->expectsOutputToContain('7C')
        // Tabel temuan per siswa tidak dicetak sama sekali — itulah buktinya
        // ketiga siswa dilipat jadi satu baris, bukan sekadar diringkas.
        ->doesntExpectOutputToContain('Temuan')
        ->assertExitCode(1);
});

test('a missing student folder is named as such, not blamed on the class', function () {
    auditedStudent(['nis' => '9001']);

    auditLocator(mock(GoogleDriveService::class, function (MockInterface $mock) {
        $mock->shouldReceive('findSchoolRoot')->andReturn('root-sekolah');
        $mock->shouldReceive('findFolder')->with('7C', 'root-sekolah')->andReturn('folder-kelas');
        $mock->shouldReceive('findFolder')->with(Mockery::any(), 'folder-kelas')->andReturnNull();
    }));

    $this->artisan('drive:audit-siswa')
        ->expectsOutputToContain('folder siswa')
        // Kali ini kebalikannya: satu baris temuan, dan kelasnya tidak dituduh.
        ->expectsOutputToContain('Temuan')
        ->doesntExpectOutputToContain('Folder kelas yang tidak ada')
        ->assertExitCode(1);
});

test('a student whose photo is only guessed is counted as needing backfill, not as broken', function () {
    auditedStudent(['nis' => '9001']);

    auditLocator(mock(GoogleDriveService::class, function (MockInterface $mock) {
        $mock->shouldReceive('findSchoolRoot')->andReturn('root-sekolah');
        $mock->shouldReceive('findFolder')->andReturn('folder-siswa');
        $mock->shouldReceive('findStudentFolderId')->andReturn('folder-siswa');
        $mock->shouldReceive('findFileByName')->andReturn([['id' => 'ketemu', 'name' => 'foto.png']]);
    }));

    $this->artisan('drive:audit-siswa')
        ->expectsOutputToContain('hanya perlu backfill id')
        ->assertExitCode(1);
});

test('without --fix not a single row is written', function () {
    $student = auditedStudent(['nis' => '9001']);

    auditLocator(mock(GoogleDriveService::class, function (MockInterface $mock) {
        $mock->shouldReceive('findSchoolRoot')->andReturn('root-sekolah');
        $mock->shouldReceive('findFolder')->andReturn('folder-siswa');
        $mock->shouldReceive('findStudentFolderId')->andReturn('folder-siswa');
        $mock->shouldReceive('findFileByName')->andReturn([['id' => 'ketemu', 'name' => 'foto.png']]);
    }));

    $this->artisan('drive:audit-siswa')->assertExitCode(1);

    expect($student->fresh()->photo_drive_file_id)->toBeNull()
        ->and($student->fresh()->drive_folder_id)->toBeNull();
});

test('--fix ignores a stale not-found left in the cache by an earlier page view', function () {
    // locate() membaca lewat cache 6 jam yang ikut menyimpan hasil "tidak
    // ketemu". Satu kunjungan operator ke halaman siswa sebelum --fix jalan akan
    // membuatnya dilewati diam-diam, dan --fix yang dijalankan dua kali menjawab
    // berbeda tanpa sebab yang terlihat.
    $student = auditedStudent(['nis' => '9001']);

    // Bentuk kuncinya milik StudentDrivePhotoLocator; ditulis ulang di sini
    // karena justru itulah yang membuat test ini menggigit.
    Cache::put('student-drive-photo:'.$student->id, [], 3600);

    auditLocator(mock(GoogleDriveService::class, function (MockInterface $mock) {
        $mock->shouldReceive('findSchoolRoot')->andReturn('root-sekolah');
        $mock->shouldReceive('findFolder')->andReturn('folder-siswa');
        $mock->shouldReceive('findStudentFolderId')->andReturn('folder-siswa');
        $mock->shouldReceive('findFileByName')->andReturn([['id' => 'ketemu', 'name' => 'foto.png']]);
    }));

    $this->artisan('drive:audit-siswa --fix')->assertExitCode(0);

    expect($student->fresh()->photo_drive_file_id)->toBe('ketemu');
});

test('a student who has never been photographed is not a finding', function () {
    auditedStudent(['nis' => '9001', 'photo_path' => null]);

    auditLocator(mock(GoogleDriveService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('findSchoolRoot');
        $mock->shouldNotReceive('findStudentFolderId');
    }));

    $this->artisan('drive:audit-siswa')
        ->expectsOutputToContain('belum pernah difoto, dilewati')
        ->assertExitCode(0);
});
