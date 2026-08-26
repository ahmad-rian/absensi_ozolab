<?php

use App\Jobs\SyncStudentDriveFolderJob;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use App\Support\StudentDriveNaming;
use Illuminate\Support\Facades\Queue;

/**
 * Membetulkan nama siswa dulu hanya mengganti nama FOLDER-nya. Isinya tetap
 * memakai slug lama, jadi pencarian berbasis nama — `StudentDrivePhotoLocator`
 * mencari `{awalan-baru}foto.png` — kembali nihil. Gejalanya sama persis dengan
 * "foto hilang" yang sudah pernah dikejar, hanya penyebabnya berbeda.
 */
function siswaBerfolder(array $atribut = []): Student
{
    $school = School::factory()->create();

    return Student::factory()->create([
        'school_id' => $school->id,
        'classroom_id' => Classroom::factory()->create(['school_id' => $school->id])->id,
        'full_name' => 'RADEN WASTU YUGA WIBOWO',
        'nis' => '17357',
        'drive_folder_id' => 'folder-siswa',
        ...$atribut,
    ]);
}

test('observer meneruskan bahwa namanya yang berubah', function () {
    Queue::fake();

    siswaBerfolder()->update(['full_name' => 'R WASTU YUGA WIBOWO']);

    Queue::assertPushed(
        SyncStudentDriveFolderJob::class,
        fn (SyncStudentDriveFolderJob $job) => $job->selaraskanBerkas === true,
    );
});

test('NIS yang diisi belakangan juga menggeser nama berkas', function () {
    Queue::fake();

    siswaBerfolder(['nis' => null])->update(['nis' => '17357']);

    Queue::assertPushed(
        SyncStudentDriveFolderJob::class,
        fn (SyncStudentDriveFolderJob $job) => $job->selaraskanBerkas === true,
    );
});

/**
 * Penjaga biaya. Nama berkas tidak memuat kelas, jadi kenaikan kelas satu
 * angkatan — ratusan job sekaligus di antrean yang dipakai bersama puluhan
 * situs — tidak boleh menambah satu pun panggilan Drive untuk menyelaraskan
 * nama.
 */
test('pindah kelas saja tidak memicu penyelarasan berkas', function () {
    Queue::fake();

    $siswa = siswaBerfolder();
    $kelasBaru = Classroom::factory()->create(['school_id' => $siswa->school_id]);

    $siswa->update(['classroom_id' => $kelasBaru->id]);

    Queue::assertPushed(
        SyncStudentDriveFolderJob::class,
        fn (SyncStudentDriveFolderJob $job) => $job->selaraskanBerkas === false,
    );
});

test('siswa tanpa folder tersimpan tidak memicu apa pun', function () {
    Queue::fake();

    siswaBerfolder(['drive_folder_id' => null])->update(['full_name' => 'NAMA BARU']);

    Queue::assertNotPushed(SyncStudentDriveFolderJob::class);
});

// --- Keputusan per berkas, tanpa menyentuh Drive sama sekali ---

test('berkas bernama basi diganti namanya', function () {
    $siswa = Student::factory()->make(['full_name' => 'R WASTU YUGA WIBOWO', 'nis' => '17357']);

    expect(StudentDriveNaming::tindakan('raden-wastu-yuga-wibowo-17357-osis.png', $siswa, []))
        ->toBe(StudentDriveNaming::GANTI_NAMA);
});

test('berkas yang namanya sudah benar dibiarkan', function () {
    $siswa = Student::factory()->make(['full_name' => 'R WASTU YUGA WIBOWO', 'nis' => '17357']);

    expect(StudentDriveNaming::tindakan('r-wastu-yuga-wibowo-17357-osis.png', $siswa, []))
        ->toBe(StudentDriveNaming::LEWATI);
});

/**
 * Inti keluhannya: folder berisi versi lama DAN versi baru berdampingan, jadi
 * isinya 6 bukan 4.
 */
test('berkas yang sudah tergantikan dibuang', function () {
    $siswa = Student::factory()->make(['full_name' => 'R WASTU YUGA WIBOWO', 'nis' => '17357']);

    expect(StudentDriveNaming::tindakan(
        'raden-wastu-yuga-wibowo-17357-osis.png',
        $siswa,
        ['r-wastu-yuga-wibowo-17357-osis.png'],
    ))->toBe(StudentDriveNaming::BUANG);
});

/**
 * NIS berpindah tangan itu nyata di data prod: berkas
 * `alfian-rifky-maulana-17336-*.png` ditemukan di folder siswa yang sekarang
 * bernama ALVIAN FAIZ SYAHPUTRA. Membuangnya berarti menghapus kartu anak lain.
 */
test('berkas milik siswa lain tidak diganti nama maupun dibuang', function () {
    $alvian = Student::factory()->make(['full_name' => 'ALVIAN FAIZ SYAHPUTRA', 'nis' => '17336']);

    expect(StudentDriveNaming::tindakan('alfian-rifky-maulana-17336-osis.png', $alvian, []))
        ->toBe(StudentDriveNaming::LEWATI)
        ->and(StudentDriveNaming::tindakan(
            'alfian-rifky-maulana-17336-osis.png',
            $alvian,
            ['alvian-faiz-syahputra-17336-osis.png'],
        ))->toBe(StudentDriveNaming::LEWATI);
});

test('berkas yang ditaruh fotografer tidak disentuh', function () {
    $siswa = Student::factory()->make(['full_name' => 'R WASTU YUGA WIBOWO', 'nis' => '17357']);

    expect(StudentDriveNaming::tindakan('DSC_0012-edit.JPG', $siswa, []))
        ->toBe(StudentDriveNaming::LEWATI)
        ->and(StudentDriveNaming::tindakan('scan.png', $siswa, []))
        ->toBe(StudentDriveNaming::LEWATI);
});
