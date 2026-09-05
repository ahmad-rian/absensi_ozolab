<?php

namespace App\Support;

use App\Models\School;

/**
 * Alamat pendek menuju halaman scan ringan: `/g/{kode}`.
 *
 * Perangkat gerbangnya box Android TV, dan alamatnya diketik dengan remote —
 * `/scan/{40 karakter}/ringan` tidak masuk akal untuk itu.
 *
 * Kodenya adalah 8 karakter PERTAMA `scanner_token`, bukan rahasia baru yang
 * disimpan terpisah. Dua akibat yang keduanya memang diinginkan: tidak ada kolom
 * dan migrasi tambahan, dan mengganti token sekolah (SchoolController::update
 * memutar ulang `scanner_token`) otomatis mematikan alamat pendeknya juga.
 *
 * `Str::random(40)` menghasilkan [A-Za-z0-9], jadi 8 karakter pertamanya sekitar
 * 47 bit — jauh di luar jangkauan tebakan, apalagi dengan throttle di rutenya.
 */
class ScannerShortLink
{
    public const PANJANG = 8;

    public static function codeFor(School $school): string
    {
        return substr((string) $school->scanner_token, 0, self::PANJANG);
    }

    /**
     * Sekolah pemilik kode, atau null kalau tidak ada / ambigu.
     *
     * Panjang dan bentuknya dikunci lebih dulu. Tanpa itu, `_` dan `%` dari
     * pemakai menjadi wildcard LIKE, dan kode sependek satu huruf cocok dengan
     * banyak sekolah sekaligus.
     */
    public static function resolve(string $kode): ?School
    {
        if (! preg_match('/^[A-Za-z0-9]{'.self::PANJANG.'}$/', $kode)) {
            return null;
        }

        $cocok = School::where('scanner_token', 'like', $kode.'%')->limit(2)->get();

        // Dua sekolah dengan awalan token sama tidak mungkin dipilih dengan
        // benar. Lebih baik gagal daripada mengarahkan operator ke gerbang
        // sekolah lain.
        return $cocok->count() === 1 ? $cocok->first() : null;
    }
}
