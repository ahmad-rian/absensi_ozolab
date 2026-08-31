<?php

use App\Jobs\PurgeStudentDriveAssetsJob;
use App\Models\School;
use App\Models\SchoolDriveConfig;
use App\Models\Student;
use Illuminate\Support\Facades\Storage;

/**
 * Job ini membuang berkas sungguhan, jadi yang diuji bukan hanya "apakah jalan"
 * melainkan "apakah ia menahan diri pada saat yang tepat".
 *
 * Jalur Drive-nya tidak bisa dipalsukan tanpa memalsukan seluruh klien Google —
 * `GoogleDriveService` membangun kliennya sendiri di konstruktor. Jadi seperti
 * `CleanDriveDuplicatesCommand::duplikat()` dan `StudentDriveNaming::tindakan()`,
 * keputusannya diuji sebagai fungsi murni, dan pemakaian primitifnya dijaga
 * dengan pemeriksaan tingkat sumber.
 */
beforeEach(function () {
    Storage::fake('public');
});

test('berkas lokal dibuang dari disk', function () {
    Storage::disk('public')->put('photos/students/x/y.png', 'foto');
    Storage::disk('public')->put('cards/x/osis.png', 'kartu');

    (new PurgeStudentDriveAssetsJob(
        schoolId: School::factory()->create()->id,
        studentId: 'siswa-terhapus',
        driveFolderId: null,
        photoPath: 'photos/students/x/y.png',
        localPaths: ['cards/x/osis.png'],
    ))->handle();

    Storage::disk('public')->assertMissing('photos/students/x/y.png');
    Storage::disk('public')->assertMissing('cards/x/osis.png');
});

test('berkas yang sudah tidak ada tidak membuat job melempar', function () {
    (new PurgeStudentDriveAssetsJob(
        schoolId: School::factory()->create()->id,
        studentId: 'siswa',
        photoPath: 'photos/tidak-ada.png',
    ))->handle();
})->throwsNoExceptions();

test('Drive yang tidak aktif tetap membersihkan berkas lokal', function () {
    Storage::disk('public')->put('photos/x.png', 'foto');

    $school = School::factory()->create();
    SchoolDriveConfig::create([
        'school_id' => $school->id,
        'root_folder_id' => 'root-sekolah',
        'is_active' => false,
    ]);

    (new PurgeStudentDriveAssetsJob(
        schoolId: $school->id,
        studentId: 'siswa',
        driveFolderId: 'folder-siswa',
        photoPath: 'photos/x.png',
    ))->handle();

    Storage::disk('public')->assertMissing('photos/x.png');
});

// --- Penjaga folder bersama, tanpa menyentuh Drive ---

/**
 * Unique index `(school_id, nis, deleted_at)` mengizinkan siswa aktif dan siswa
 * terhapus memegang NIS yang sama. Kalau nama dan kelasnya juga sama, keduanya
 * menurunkan nama folder yang sama dan `findOrCreateFolder()` memberi id yang
 * sama. Membuangnya berarti menghapus berkas siswa yang MASIH HIDUP.
 */
test('folder yang masih dipakai siswa aktif lain tidak boleh dibuang', function () {
    $siswaHidup = Student::factory()->create(['drive_folder_id' => 'folder-berbagi']);

    expect(PurgeStudentDriveAssetsJob::folderBolehDibuang('folder-berbagi', 'siswa-lain'))
        ->toBeFalse()
        ->and($siswaHidup->fresh()->drive_folder_id)->toBe('folder-berbagi');
});

/**
 * Kalau siswa yang di-soft-delete ikut dihitung, folder tidak akan pernah bisa
 * dibuang sama sekali — siswa yang barusan dihapus itu sendiri masih punya baris
 * yang menunjuk ke sana.
 */
test('siswa yang sudah terhapus tidak menahan pembuangan', function () {
    $terhapus = Student::factory()->create(['drive_folder_id' => 'folder-siswa']);
    $terhapus->delete();

    expect(PurgeStudentDriveAssetsJob::folderBolehDibuang('folder-siswa', 'siswa-ketiga'))->toBeTrue();
});

test('folder milik siswa yang sedang dihapus itu sendiri boleh dibuang', function () {
    $siswa = Student::factory()->create(['drive_folder_id' => 'folder-siswa']);

    expect(PurgeStudentDriveAssetsJob::folderBolehDibuang('folder-siswa', $siswa->id))->toBeTrue();
});

test('tanpa id folder tidak ada yang boleh dibuang', function () {
    expect(PurgeStudentDriveAssetsJob::folderBolehDibuang(null, 'siswa'))->toBeFalse();
});

/**
 * Pembeda sampah versus hapus permanen, dan satu-satunya hal yang benar-benar
 * membedakan fitur ini dari kehilangan data. `GoogleDriveService::delete()` nol
 * pemanggil di seluruh kode dan harus tetap begitu.
 */
test('job membuang ke sampah, tidak pernah menghapus permanen', function () {
    $sumber = file_get_contents(
        (new ReflectionClass(PurgeStudentDriveAssetsJob::class))->getFileName()
    );

    // `->delete(` sendirian tidak cukup spesifik: berkas LOKAL memang dibuang
    // dengan `$disk->delete()`. Yang tidak boleh ada adalah penghapus permanen
    // di sisi Drive.
    expect($sumber)->toContain('->trashFile(')
        ->and($sumber)->not->toMatch('/forSchool\([^)]*\)->delete\(/');
});
