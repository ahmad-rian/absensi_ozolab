<?php

use App\Console\Commands\RestoreDriveTrashCommand;

/**
 * Penyaringnya yang menentukan apakah pemulihan menyasar satu kejadian atau
 * menghidupkan kembali seluruh isi sampah. Diuji langsung, tanpa Drive.
 */
$sampah = [
    ['id' => 'a', 'name' => 'r-wastu-yuga-wibowo-17357-foto.png', 'trashedTime' => '2026-08-23T17:10:00.000Z'],
    ['id' => 'b', 'name' => 'r-wastu-yuga-wibowo-17357-osis.png', 'trashedTime' => '2026-08-23T17:10:00.000Z'],
    ['id' => 'c', 'name' => 'kenzie-al-rasyiid-17443-4r_3x4.png', 'trashedTime' => '2026-08-23T17:10:00.000Z'],
    ['id' => 'lama', 'name' => 'berkas-lama.png', 'trashedTime' => '2026-03-01T08:00:00.000Z'],
];

test('penyaring nama hanya mengambil berkas siswa yang dimaksud', function () use ($sampah) {
    expect(array_column(RestoreDriveTrashCommand::saring($sampah, '17357', null), 'id'))
        ->toBe(['a', 'b']);
});

test('penyaring nama tidak peduli huruf besar kecil', function () use ($sampah) {
    expect(RestoreDriveTrashCommand::saring($sampah, 'R-WASTU', null))->toHaveCount(2);
});

/**
 * Berkas yang dibuang jauh sebelum perintah pembersih dijalankan tidak boleh
 * ikut hidup lagi — itu keputusan orang, bukan kecelakaan.
 */
test('penyaring waktu meninggalkan sampah lama', function () use ($sampah) {
    expect(array_column(RestoreDriveTrashCommand::saring($sampah, null, '2026-08-23'), 'id'))
        ->toBe(['a', 'b', 'c']);
});

test('dua penyaring dipakai bersamaan saling mempersempit', function () use ($sampah) {
    expect(array_column(RestoreDriveTrashCommand::saring($sampah, '17357', '2026-08-23'), 'id'))
        ->toBe(['a', 'b']);
});

/**
 * Umur yang tidak diketahui lebih baik ikut dipulihkan daripada tertinggal:
 * memulihkan kelebihan bisa dibatalkan, kehilangan tidak.
 */
test('berkas tanpa waktu pembuangan tetap lolos penyaring waktu', function () {
    $tanpaWaktu = [['id' => 'x', 'name' => 'entah.png', 'trashedTime' => null]];

    expect(RestoreDriveTrashCommand::saring($tanpaWaktu, null, '2026-08-23'))->toHaveCount(1);
});

test('tanpa penyaring apa pun perintahnya menolak jalan', function () {
    $this->artisan('drive:pulihkan-sampah')
        ->expectsOutputToContain('SELURUH isi sampah')
        ->assertFailed();
});

test('dengan --semua perintahnya mau jalan', function () {
    $this->artisan('drive:pulihkan-sampah', ['--semua' => true, '--dry-run' => true])
        ->assertSuccessful();
});

test('penyaring nama saja sudah cukup untuk jalan', function () {
    $this->artisan('drive:pulihkan-sampah', ['--cocok' => '17357', '--dry-run' => true])
        ->assertSuccessful();
});
