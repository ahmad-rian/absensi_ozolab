<?php

namespace App\Support;

/**
 * NIS sementara untuk siswa yang mendaftar tanpa nomor induk.
 *
 * `students.nis` NOT NULL dan unik per sekolah, jadi kolomnya harus berisi
 * sesuatu. Pembuat dan pengenalnya sengaja tinggal di satu tempat supaya
 * bentuknya tidak bisa bergeser sendiri-sendiri.
 */
class NisSementara
{
    /** Tahun empat digit disusul delapan digit acak. */
    private const POLA = '/^20\d{2}\d{8}$/';

    public static function buat(): string
    {
        return now()->format('Y').str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
    }

    /**
     * Berbentuk NIS sementara?
     *
     * Ini pengenalan bentuk, bukan bukti: NIS asli 12 digit berawalan tahun
     * akan ikut cocok. Karena itu hasilnya hanya boleh dipakai untuk hal yang
     * aman bila keliru - mis. mengosongkan sel ekspor, yang saat diunggah
     * ulang berarti "biarkan nilai lama".
     */
    public static function tampak(?string $nis): bool
    {
        return $nis !== null && preg_match(self::POLA, $nis) === 1;
    }
}
