<?php

use App\Models\Student;
use App\Services\CardGeneratorService;
use App\Services\GoogleDriveService;
use App\Services\PhotoSheetGeneratorService;
use App\Support\StudentDriveNaming;

test('jenis keluaran dikenali dari ekor nama berkas', function () {
    expect(StudentDriveNaming::jenisDari('r-wastu-17357-osis.png'))->toBe('osis')
        ->and(StudentDriveNaming::jenisDari('r-wastu-17357-perpustakaan.png'))->toBe('perpustakaan')
        ->and(StudentDriveNaming::jenisDari('r-wastu-17357-foto.png'))->toBe('foto');
});

/**
 * Template `4r_3x4` memakai garis bawah, bukan tanda hubung. Kalau pemisahnya
 * salah, jenisnya terpotong jadi `3x4` dan berkasnya tidak dikenali lagi.
 */
test('jenis bergaris bawah tidak terpotong', function () {
    foreach (array_keys(PhotoSheetGeneratorService::TEMPLATES) as $template) {
        expect(StudentDriveNaming::jenisDari("r-wastu-17357-{$template}.png"))->toBe($template);
    }
});

/**
 * Folder siswa juga berisi berkas yang ditaruh fotografer dengan nama bebas.
 * Kalau salah satunya dikira keluaran aplikasi, jalur unggah akan menimpanya.
 */
test('berkas di luar keluaran aplikasi tidak punya jenis', function () {
    expect(StudentDriveNaming::jenisDari('DSC_0012-edit.JPG'))->toBeNull()
        ->and(StudentDriveNaming::jenisDari('foto-anak-bagus.png'))->toBeNull()
        ->and(StudentDriveNaming::jenisDari('scan.png'))->toBeNull();
});

test('awalan disusun dari slug nama dan NIS', function () {
    $siswa = Student::factory()->make(['full_name' => 'R WASTU YUGA WIBOWO', 'nis' => '17357']);

    expect(StudentDriveNaming::prefix($siswa))->toBe('r-wastu-yuga-wibowo-17357-');
});

/**
 * NIS bernilai string kosong dulu menghasilkan dua bentuk nama BERBEDA di folder
 * yang sama: kartu dan lembar pas foto memakai `??` sehingga jatuh ke `''`
 * (`{slug}--osis.png`), sementara foto siswa memakai `?:` sehingga jatuh ke ULID.
 * Tidak ada satu pun jalur baca yang menduga itu.
 */
test('NIS kosong jatuh ke ULID, bukan ke string kosong', function () {
    $siswa = Student::factory()->make([
        'id' => '01K3NRFULANFULANFULAN0',
        'full_name' => 'FULAN',
        'nis' => '',
    ]);

    expect(StudentDriveNaming::prefix($siswa))->toBe('fulan-01K3NRFULANFULANFULAN0-')
        ->and(StudentDriveNaming::prefix($siswa))->not->toContain('--');
});

test('GoogleDriveService memakai awalan yang sama', function () {
    $siswa = Student::factory()->make(['full_name' => 'FULAN BIN FULAN', 'nis' => '17357']);

    $awalan = StudentDriveNaming::prefix($siswa);

    expect(GoogleDriveService::studentFilePrefix($siswa))->toBe($awalan)
        ->and(GoogleDriveService::studentPhotoFileName($siswa))->toBe($awalan.'foto.png');
});

/**
 * Kartu dan lembar pas foto menyusun nama berkas LOKAL, dan `basename()`-nya
 * itulah yang jadi nama di Drive. Keduanya dulu menyalin aturannya sendiri dan
 * menyimpang diam-diam. Ini penjaga supaya salinan itu tidak kembali — memanggil
 * metodenya langsung tidak mungkin, keduanya merender lewat Browsershot.
 */
test('kartu dan lembar pas foto tidak menyusun awalan sendiri', function (string $kelas) {
    $sumber = file_get_contents((new ReflectionClass($kelas))->getFileName());

    expect($sumber)->toContain('StudentDriveNaming::prefix(')
        ->and($sumber)->not->toContain('Str::slug($student->full_name)');
})->with([
    CardGeneratorService::class,
    PhotoSheetGeneratorService::class,
]);

test('nama yang sudah benar tidak diselaraskan ulang', function () {
    expect(StudentDriveNaming::namaSelaras('r-wastu-17357-osis.png', 'r-wastu-17357-'))->toBeNull();
});

test('nama basi diselaraskan ke awalan sekarang', function () {
    expect(StudentDriveNaming::namaSelaras('raden-wastu-yuga-wibowo-17357-foto.png', 'r-wastu-yuga-wibowo-17357-'))
        ->toBe('r-wastu-yuga-wibowo-17357-foto.png');
});
