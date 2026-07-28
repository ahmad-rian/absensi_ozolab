<?php

use App\Services\GoogleDriveService;

/**
 * Pencarian foto pendaftaran memakai `name contains` untuk menjaring kandidat,
 * tapi yang menentukan adalah pemilih ini. Selama ia hanya menerima nama yang
 * sama persis (dengan atau tanpa ekstensi), mengetik sepotong nama tidak bisa
 * dipakai memanen berkas orang lain.
 */
test('nama tanpa ekstensi menemukan berkas yang berekstensi', function () {
    $files = [
        ['id' => 'a', 'name' => 'DSC_0012.JPG', 'modifiedTime' => '2026-07-02T00:00:00Z'],
    ];

    expect(GoogleDriveService::pickPhotoCandidate($files, 'DSC_0012'))
        ->toBe(['id' => 'a', 'name' => 'DSC_0012.JPG']);
});

test('nama berekstensi menemukan berkas yang tersimpan tanpa ekstensi', function () {
    $files = [
        ['id' => 'a', 'name' => '_DSC0019', 'modifiedTime' => '2026-07-02T00:00:00Z'],
    ];

    expect(GoogleDriveService::pickPhotoCandidate($files, '_DSC0019.JPG'))
        ->toBe(['id' => 'a', 'name' => '_DSC0019']);
});

test('cocok persis menang atas yang hanya sama nama dasarnya', function () {
    $files = [
        ['id' => 'png', 'name' => 'DSC_0012.png', 'modifiedTime' => '2026-07-05T00:00:00Z'],
        ['id' => 'exact', 'name' => 'DSC_0012.JPG', 'modifiedTime' => '2026-07-02T00:00:00Z'],
    ];

    expect(GoogleDriveService::pickPhotoCandidate($files, 'DSC_0012.JPG')['id'])->toBe('exact');
});

test('tanpa yang cocok persis, yang paling baru diubah yang dipakai', function () {
    $files = [
        ['id' => 'lama', 'name' => 'DSC_0012.JPG', 'modifiedTime' => '2026-07-02T00:00:00Z'],
        ['id' => 'baru', 'name' => 'DSC_0012.png', 'modifiedTime' => '2026-07-05T00:00:00Z'],
    ];

    expect(GoogleDriveService::pickPhotoCandidate($files, 'DSC_0012')['id'])->toBe('baru');
});

test('nama yang hanya mengandung kata kunci ditolak', function () {
    $files = [
        ['id' => 'a', 'name' => 'DSC_0012-edit.JPG', 'modifiedTime' => '2026-07-02T00:00:00Z'],
        ['id' => 'b', 'name' => 'copy of DSC_0012.JPG', 'modifiedTime' => '2026-07-03T00:00:00Z'],
    ];

    expect(GoogleDriveService::pickPhotoCandidate($files, 'DSC_0012'))->toBeNull();
});

test('beda kapital tetap dianggap cocok', function () {
    $files = [
        ['id' => 'a', 'name' => 'dsc_0012.jpg', 'modifiedTime' => '2026-07-02T00:00:00Z'],
    ];

    expect(GoogleDriveService::pickPhotoCandidate($files, 'DSC_0012')['id'])->toBe('a');
});

test('daftar kosong dan berkas tanpa modifiedTime tidak bikin error', function () {
    expect(GoogleDriveService::pickPhotoCandidate([], 'DSC_0012'))->toBeNull();

    $files = [['id' => 'a', 'name' => 'DSC_0012.JPG']];

    expect(GoogleDriveService::pickPhotoCandidate($files, 'DSC_0012')['id'])->toBe('a');
});
