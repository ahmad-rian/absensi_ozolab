<?php

use App\Console\Commands\NotifyAttendanceAbsenceCommand;

/**
 * Pengelompokan pesan bergantung sepenuhnya pada fungsi ini: dua bentuk nomor
 * yang sama harus menghasilkan kunci yang sama, kalau tidak satu keluarga tetap
 * menerima dua pesan.
 */
test('nol depan, kode negara, dan pemisah diseragamkan', function (string $masukan, string $harapan) {
    expect(NotifyAttendanceAbsenceCommand::nomorSeragam($masukan))->toBe($harapan);
})->with([
    ['081391444323', '81391444323'],
    ['81391444323', '81391444323'],
    ['6281391444323', '81391444323'],
    ['+6281391444323', '81391444323'],
    ['+62 813-9144-4323', '81391444323'],
    ['0813 9144 4323', '81391444323'],
]);

/**
 * Nomor faker Amerika ada di data prod. Ia tidak boleh diperlakukan seolah
 * berkode Indonesia, tapi juga tidak boleh menabrak nomor lain.
 */
test('nomor luar negeri tidak dipaksa jadi nomor Indonesia', function () {
    expect(NotifyAttendanceAbsenceCommand::nomorSeragam('+1 (862) 357-3905'))->toBe('18623573905')
        ->and(NotifyAttendanceAbsenceCommand::nomorSeragam('+1 (558) 363-9813'))->toBe('15583639813');
});

test('nomor kosong tidak melempar galat', function () {
    expect(NotifyAttendanceAbsenceCommand::nomorSeragam(''))->toBe('')
        ->and(NotifyAttendanceAbsenceCommand::nomorSeragam('-'))->toBe('');
});
