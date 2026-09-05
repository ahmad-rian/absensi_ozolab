<?php

namespace App\Support;

use App\Models\School;
use Illuminate\Validation\Rule;

/**
 * Alamat pendek menuju halaman scan ringan: `/g/{kode}`.
 *
 * Perangkat gerbangnya box Android TV, dan alamatnya diketik dengan remote —
 * `/scan/{40 karakter}/ringan` tidak masuk akal untuk itu.
 *
 * Dua bentuk kode, keduanya berlaku bersamaan:
 *
 * 1. Alias pilihan sendiri, mis. `tyas-photo`, disimpan di `scan_short_code`.
 * 2. Cadangan: 8 karakter PERTAMA `scanner_token`, tanpa perlu diatur apa pun.
 *
 * Alias diperiksa lebih dulu. Alias yang bentrok dengan awalan token sekolah
 * lain ditolak saat disimpan (lihat rules()), jadi urutan ini tidak bisa dipakai
 * membajak alamat sekolah lain.
 *
 * Cadangan berbasis token punya sifat yang memang diinginkan: memutar ulang
 * `scanner_token` otomatis mematikan alamat pendeknya juga. Alias yang dinamai
 * sendiri TIDAK ikut mati — itu memang gunanya, dan mencabutnya dilakukan dengan
 * mengosongkan kolomnya.
 */
class ScannerShortLink
{
    /** Panjang potongan token yang dipakai sebagai kode cadangan. */
    public const PANJANG = 8;

    /**
     * Kode yang berlaku untuk sekolah ini — alias kalau ada, potongan token
     * kalau belum pernah diatur.
     */
    public static function codeFor(School $school): string
    {
        return $school->scan_short_code ?: substr((string) $school->scanner_token, 0, self::PANJANG);
    }

    /**
     * Sekolah pemilik kode, atau null kalau tidak ada / ambigu.
     *
     * Bentuknya dikunci lebih dulu. Tanpa itu, `_` dan `%` dari pemakai menjadi
     * wildcard LIKE dan `/g/________` cocok dengan token sekolah mana pun —
     * operator mendarat di gerbang sekolah lain.
     */
    public static function resolve(string $kode): ?School
    {
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,31}$/', $kode)) {
            return null;
        }

        if ($alias = School::where('scan_short_code', mb_strtolower($kode))->first()) {
            return $alias;
        }

        // Cadangan hanya menerima kode sepanjang potongan tokennya. Tanpa
        // penjaga panjang ini, kode pendek cocok dengan banyak sekolah.
        if (strlen($kode) !== self::PANJANG || ! ctype_alnum($kode)) {
            return null;
        }

        $cocok = School::where('scanner_token', 'like', $kode.'%')->limit(2)->get();

        // Dua sekolah dengan awalan token sama tidak mungkin dipilih dengan
        // benar. Lebih baik gagal daripada menebak.
        return $cocok->count() === 1 ? $cocok->first() : null;
    }

    public static function normalize(?string $kode): ?string
    {
        $kode = mb_strtolower(trim((string) $kode));

        return $kode === '' ? null : $kode;
    }

    /**
     * Aturan validasi alias untuk satu sekolah.
     *
     * @return array<int, mixed>
     */
    public static function rules(School $school): array
    {
        return [
            'nullable',
            'string',
            'min:3',
            'max:32',
            // Diawali huruf/angka, lalu boleh strip dan garis bawah. Titik dan
            // garis miring dilarang: keduanya mengubah bentuk jalur URL-nya.
            'regex:/^[a-z0-9][a-z0-9_-]*$/',
            Rule::unique('schools', 'scan_short_code')->ignore($school->id),
            // Alias yang sama dengan awalan token sekolah mana pun akan
            // membayangi alamat cadangan sekolah itu. Ditolak di sini, bukan
            // dibiarkan jadi rebutan diam-diam saat scan.
            function (string $atribut, mixed $nilai, callable $gagal) {
                if (School::where('scanner_token', 'like', $nilai.'%')->exists()) {
                    $gagal('Kode ini bentrok dengan link scan bawaan sebuah sekolah. Pilih kode lain.');
                }
            },
        ];
    }
}
