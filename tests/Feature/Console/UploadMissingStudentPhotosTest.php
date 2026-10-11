<?php

use App\Console\Commands\UploadMissingStudentPhotosCommand as Perintah;
use App\Models\Student;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

function siswaPunyaPasFoto(?string $folder = 'folder-siswa'): Student
{
    return Student::factory()->make([
        'full_name' => 'R WASTU YUGA WIBOWO',
        'nis' => '17357',
        'photo_path' => 'students/photos/wastu.jpg',
        'drive_folder_id' => $folder,
    ]);
}

test('folder tanpa berkas foto diunggah', function () {
    expect(Perintah::keputusan(siswaPunyaPasFoto(), [
        ['id' => 'a', 'name' => 'r-wastu-yuga-wibowo-17357-osis.jpg'],
    ], true))->toBe(Perintah::UNGGAH);
});

test('foto yang sudah ada di Drive tidak diunggah ulang, apa pun ekstensinya', function (string $nama) {
    expect(Perintah::keputusan(siswaPunyaPasFoto(), [['id' => 'a', 'name' => $nama]], true))
        ->toBe(Perintah::SUDAH_ADA);
})->with([
    'png lama' => 'r-wastu-yuga-wibowo-17357-foto.png',
    'jpg baru' => 'r-wastu-yuga-wibowo-17357-foto.jpg',
    'nama basi' => 'raden-wastu-yuga-wibowo-17357-foto.png',
]);

/**
 * Foto asli fotografer bukan pas foto aplikasi; ia tidak menggantikan
 * `…-foto.jpg` dan tidak pernah disentuh.
 */
test('foto asli fotografer tidak dihitung sebagai pas foto', function () {
    expect(Perintah::keputusan(siswaPunyaPasFoto(), [
        ['id' => 'asli', 'name' => 'IMG_1234.JPG'],
    ], true))->toBe(Perintah::UNGGAH);
});

test('siswa tanpa folder dilewati, bukan dibuatkan folder berisi foto saja', function () {
    expect(Perintah::keputusan(siswaPunyaPasFoto(null), [], true))->toBe(Perintah::TANPA_FOLDER);
});

test('berkas lokal yang hilang dilaporkan, bukan diunggah', function () {
    expect(Perintah::keputusan(siswaPunyaPasFoto(), [], false))->toBe(Perintah::BERKAS_HILANG);
});

test('perintah jalan walau belum ada sekolah dengan Drive aktif', function () {
    $this->artisan('drive:unggah-foto-siswa')
        ->expectsOutputToContain('Tidak ada sekolah dengan Google Drive aktif.')
        ->assertSuccessful();
});
