<?php

use App\Services\PhotoSheetBatchService;

/**
 * Penyusunan slot adalah inti fitur ini: salah membagi berarti kertas terbuang
 * atau foto siswa tercecer di lembar yang berbeda.
 */
test('pesanan beberapa siswa muat dalam satu lembar penuh', function () {
    $items = [
        ['student_id' => 'ana', 'quantity' => 4],
        ['student_id' => 'budi', 'quantity' => 2],
        ['student_id' => 'citra', 'quantity' => 1],
        ['student_id' => 'dedi', 'quantity' => 1],
    ];

    $pages = PhotoSheetBatchService::paginate($items, 8);

    expect($pages)->toHaveCount(1);
    expect($pages[0])->toBe(['ana', 'ana', 'ana', 'ana', 'budi', 'budi', 'citra', 'dedi']);
});

test('slot satu siswa berurutan, tidak diselang-seling', function () {
    $items = [
        ['student_id' => 'ana', 'quantity' => 2],
        ['student_id' => 'budi', 'quantity' => 2],
    ];

    $pages = PhotoSheetBatchService::paginate($items, 8);

    expect(array_slice($pages[0], 0, 4))->toBe(['ana', 'ana', 'budi', 'budi']);
});

test('lembar terakhir yang tidak penuh diisi slot kosong', function () {
    $items = [
        ['student_id' => 'ana', 'quantity' => 8],
        ['student_id' => 'budi', 'quantity' => 4],
    ];

    $pages = PhotoSheetBatchService::paginate($items, 8);

    expect($pages)->toHaveCount(2);
    expect($pages[0])->each->toBe('ana');
    expect($pages[1])->toBe(['budi', 'budi', 'budi', 'budi', null, null, null, null]);
});

test('pesanan satu siswa melewati beberapa lembar', function () {
    $pages = PhotoSheetBatchService::paginate([['student_id' => 'ana', 'quantity' => 20]], 8);

    expect($pages)->toHaveCount(3);
    expect($pages[2])->toBe(['ana', 'ana', 'ana', 'ana', null, null, null, null]);
});

test('pesanan kosong tidak menghasilkan lembar sama sekali', function () {
    expect(PhotoSheetBatchService::paginate([], 8))->toBe([]);
    expect(PhotoSheetBatchService::paginate([['student_id' => 'ana', 'quantity' => 0]], 8))->toBe([]);
});

test('kapasitas dibaca dari template yang sama dengan lembar per-siswa', function () {
    expect(PhotoSheetBatchService::capacity('4r_3x4'))->toBe(8);
    expect(PhotoSheetBatchService::capacity('4r_2x3'))->toBe(18);
    expect(PhotoSheetBatchService::capacity('4r_4x6'))->toBe(4);
    // Template asing jatuh ke default, bukan melempar error.
    expect(PhotoSheetBatchService::capacity('tidak-ada'))->toBe(8);
});

test('jumlah lembar dan total slot dihitung konsisten', function () {
    $items = [
        ['student_id' => 'ana', 'quantity' => 5],
        ['student_id' => 'budi', 'quantity' => 4],
    ];

    expect(PhotoSheetBatchService::totalSlots($items))->toBe(9);
    expect(PhotoSheetBatchService::pageCount($items, 8))->toBe(2);
    expect(PhotoSheetBatchService::pageCount($items, 18))->toBe(1);
});
