<?php

use App\Console\Commands\MergeStudentDriveFoldersCommand;
use App\Models\Student;
use App\Services\PhotoSheetGeneratorService;

/**
 * Folder siswa terbelah karena letaknya dulu selalu diturunkan dari nama, dan
 * nama itu bergeser tiap kali kelas diganti nama, siswa naik kelas, atau NIS
 * diisi belakangan. Keputusan "folder mana milik siapa" dan "mana yang jadi
 * tujuan" diuji langsung di sini, tanpa menyentuh Drive.
 */
$folders = [
    ['id' => 'lama', 'name' => '17357 - R Wastu Yuga Wibowo'],
    ['id' => 'baru', 'name' => '17357 - R WASTU YUGA WIBOWO'],
    ['id' => 'lain', 'name' => '17358 - Siswa Lain'],
    ['id' => 'mirip', 'name' => '173570 - NIS Kepanjangan'],
];

test('folder milik satu siswa dikumpulkan tanpa peduli huruf besar kecil', function () use ($folders) {
    expect(array_column(MergeStudentDriveFoldersCommand::folderMilik($folders, '17357 - '), 'id'))
        ->toBe(['lama', 'baru']);
});

/**
 * Tanpa pemisah ` - ` ikut dibawa, awalan `17357` akan menyambar folder milik
 * siswa ber-NIS `173570` dan memindahkan berkas orang lain.
 */
test('NIS yang berawalan sama tidak ikut tersambar', function () use ($folders) {
    $hasil = MergeStudentDriveFoldersCommand::folderMilik($folders, '17357 - ');

    expect(array_column($hasil, 'id'))->not->toContain('mirip')
        ->and(array_column($hasil, 'id'))->not->toContain('lain');
});

test('siswa yang foldernya cuma satu tidak menghasilkan apa-apa', function () {
    expect(MergeStudentDriveFoldersCommand::folderMilik([
        ['id' => 'satu', 'name' => '17357 - R Wastu'],
    ], '17357 - '))->toHaveCount(1);
});

/**
 * Yang ditunjuk `drive_folder_id` menang: di sanalah aplikasi mencari dan hasil
 * generate berikutnya mendarat.
 */
test('folder yang sedang ditunjuk jadi tujuan walau isinya lebih sedikit', function () use ($folders) {
    $milikDia = MergeStudentDriveFoldersCommand::folderMilik($folders, '17357 - ');

    expect(MergeStudentDriveFoldersCommand::pilihTujuan($milikDia, 'baru', ['lama' => 3, 'baru' => 2]))
        ->toBe('baru');
});

test('tanpa penunjuk, yang isinya terbanyak yang jadi tujuan', function () use ($folders) {
    $milikDia = MergeStudentDriveFoldersCommand::folderMilik($folders, '17357 - ');

    expect(MergeStudentDriveFoldersCommand::pilihTujuan($milikDia, null, ['lama' => 3, 'baru' => 2]))
        ->toBe('lama');
});

/**
 * Penunjuk bisa menggantung ke folder yang sudah tidak ada. Ia harus diabaikan,
 * bukan dipakai lalu memindahkan semua berkas ke folder yang tidak ada.
 */
test('penunjuk yang menggantung diabaikan', function () use ($folders) {
    $milikDia = MergeStudentDriveFoldersCommand::folderMilik($folders, '17357 - ');

    expect(MergeStudentDriveFoldersCommand::pilihTujuan($milikDia, 'folder-sudah-mati', ['lama' => 3, 'baru' => 2]))
        ->toBe('lama');
});

test('awalan disusun dari NIS, dan jatuh ke ULID saat NIS kosong', function () {
    $berNis = Student::factory()->make(['nis' => '17357']);
    $tanpaNis = Student::factory()->make(['nis' => null]);

    expect(MergeStudentDriveFoldersCommand::awalan($berNis))->toBe('17357 - ')
        ->and(MergeStudentDriveFoldersCommand::awalan($tanpaNis))->toBe($tanpaNis->id.' - ');
});

/**
 * NIS salah ketik lalu dibetulkan membuat NIS itu berpindah tangan, dan folder
 * milik siswa LAIN tertinggal di bawahnya. Terjadi sungguhan: folder
 * "17346 - Ibrahim sabian alghifari" ikut terkumpul untuk siswa ber-NIS 17346
 * yang sekarang bernama Hyacintha Althafah Calluella — dua anak berbeda.
 */
test('nama yang sama sekali tidak beririsan ditolak', function () {
    expect(MergeStudentDriveFoldersCommand::namaMirip('17346 - Ibrahim sabian alghifari', 'HYACINTHA ALTHAFAH CALLUELLA'))
        ->toBeFalse();
});

test('nama yang disingkat tetap dianggap orang yang sama', function () {
    expect(MergeStudentDriveFoldersCommand::namaMirip('17357 - RADEN WASTU YUGA WIBOWO', 'R WASTU YUGA WIBOWO'))
        ->toBeTrue();
});

test('beda kapitalisasi saja jelas orang yang sama', function () {
    expect(MergeStudentDriveFoldersCommand::namaMirip('1685 - Tiara Tri Rahayu', 'TIARA TRI RAHAYU'))
        ->toBeTrue();
});

/**
 * NIS-nya ikut terbaca sebagai teks, tapi angka bukan kata — kalau ikut
 * dihitung, setiap folder akan beririsan dengan setiap siswa lewat NIS-nya
 * sendiri dan pengaman ini tidak menjaga apa pun.
 */
test('angka NIS tidak dihitung sebagai kesamaan nama', function () {
    expect(MergeStudentDriveFoldersCommand::namaMirip('17346 - Ibrahim', '17346'))->toBeTrue()
        ->and(MergeStudentDriveFoldersCommand::namaMirip('17346 - Ibrahim', 'HYACINTHA CALLUELLA'))->toBeFalse();
});

/**
 * Kata dua huruf beririsan terlalu mudah untuk bisa dipercaya sebagai bukti.
 */
test('kata pendek tidak cukup jadi bukti', function () {
    expect(MergeStudentDriveFoldersCommand::namaMirip('1 - AL FATIH', 'DE SANTOS'))->toBeFalse();
});

test('nama yang terlalu pendek untuk dinilai tidak diblokir', function () {
    expect(MergeStudentDriveFoldersCommand::namaMirip('1685 - ', 'TIARA'))->toBeTrue();
});

/**
 * Nama berkas juga diturunkan dari nama siswa. Memindahkannya saja tidak cukup:
 * halaman siswa mencari `{slug-nama-sekarang}-{nis}-foto.png` sementara yang
 * ada di folder itu masih memakai slug nama lama, dan pencariannya tetap nihil.
 */
test('nama berkas diselaraskan dengan nama siswa sekarang', function () {
    expect(MergeStudentDriveFoldersCommand::namaSelaras('raden-wastu-yuga-wibowo-17357-foto.png', 'r-wastu-yuga-wibowo-17357-'))
        ->toBe('r-wastu-yuga-wibowo-17357-foto.png');
});

/**
 * Template `4r_3x4` memakai garis bawah, bukan tanda hubung. Kalau pemisahnya
 * salah, jenis keluarannya terpotong jadi `3x4.png` dan berkasnya jadi tidak
 * dikenali lagi.
 */
test('jenis keluaran yang mengandung garis bawah tidak terpotong', function () {
    expect(MergeStudentDriveFoldersCommand::namaSelaras('raden-wastu-17357-4r_3x4.png', 'r-wastu-17357-'))
        ->toBe('r-wastu-17357-4r_3x4.png');
});

test('berkas yang namanya sudah benar tidak diubah', function () {
    expect(MergeStudentDriveFoldersCommand::namaSelaras('r-wastu-17357-osis.png', 'r-wastu-17357-'))
        ->toBeNull();
});

test('nama tanpa tanda hubung dibiarkan apa adanya', function () {
    expect(MergeStudentDriveFoldersCommand::namaSelaras('scan.png', 'r-wastu-17357-'))->toBeNull();
});

/**
 * Folder siswa juga bisa berisi berkas yang ditaruh fotografer dengan nama
 * bebas. Mengganti namanya berdasarkan pola tebakan akan merusak berkas yang
 * tidak ada hubungannya dengan aplikasi.
 */
test('berkas di luar keluaran aplikasi tidak disentuh', function () {
    expect(MergeStudentDriveFoldersCommand::namaSelaras('foto-anak-bagus.png', 'r-wastu-17357-'))->toBeNull()
        ->and(MergeStudentDriveFoldersCommand::namaSelaras('DSC_0012-edit.JPG', 'r-wastu-17357-'))->toBeNull();
});

test('setiap template pas foto dikenali', function () {
    foreach (array_keys(PhotoSheetGeneratorService::TEMPLATES) as $template) {
        expect(MergeStudentDriveFoldersCommand::namaSelaras("lama-17357-{$template}.png", 'baru-17357-'))
            ->toBe("baru-17357-{$template}.png");
    }
});

test('awalan berkas mengikuti slug nama dan NIS', function () {
    $siswa = Student::factory()->make(['full_name' => 'R WASTU YUGA WIBOWO', 'nis' => '17357']);

    expect(MergeStudentDriveFoldersCommand::awalanBerkas($siswa))->toBe('r-wastu-yuga-wibowo-17357-');
});

test('perintahnya jalan walau belum ada sekolah yang siap', function () {
    $this->artisan('drive:satukan-folder-siswa', ['--dry-run' => true])
        ->assertSuccessful();
});
