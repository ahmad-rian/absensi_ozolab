<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Menolak kata sandi yang paling sering ditebak lebih dulu.
 *
 * Akun orang tua sengaja TIDAK dibebani aturan komposisi (huruf besar, angka,
 * simbol): yang mengisinya orang tua yang banyak di antaranya gagap teknologi,
 * dan aturan komposisi pada kelompok itu menghasilkan sandi yang ditulis di
 * kertas atau dibuat seragam sekeluarga — lebih buruk daripada delapan huruf
 * yang benar-benar diingat. Ini juga sikap NIST SP 800-63B: panjang minimum
 * plus daftar-tolak, bukan aturan komposisi.
 *
 * Yang menggantikan aturan komposisi itu ada dua, dan keduanya tidak menambah
 * beban satu ketukan pun bagi pengisinya:
 *
 * 1. Daftar-tolak di bawah — menutup tebakan pertama penyerang, yang memang
 *    dari sanalah hampir semua pembobolan sandi lemah berhasil.
 * 2. `$tambahan` — sandi tidak boleh sama dengan data yang tertulis di formulir
 *    yang sama (nomor WhatsApp, bagian depan email). Nomor itu diketik dua
 *    kolom di atas kolom sandi, jadi orang yang memegang formulirnya sudah
 *    memegang tebakan terbaiknya.
 *
 * Pintu login sendiri dibatasi 5 percobaan per menit per email+IP
 * (FortifyServiceProvider::configureRateLimiting), jadi penyerang tidak bisa
 * menyapu daftar kata mana pun dari luar.
 */
class SandiUmum implements ValidationRule
{
    /**
     * Daftar-tolak pendek dan sengaja tidak lengkap.
     *
     * Isinya sandi yang lolos ambang delapan karakter DAN ada di puncak setiap
     * daftar bocoran, ditambah beberapa yang khas Indonesia. Daftar panjang
     * (rockyou dan sejenisnya) tidak dimuat di sini: berkas puluhan megabita di
     * jalur permintaan publik itu ongkos yang tidak sebanding dengan tambahan
     * perlindungannya di atas pembatasan laju login.
     *
     * @var list<string>
     */
    private const DAFTAR_TOLAK = [
        'password', 'password1', 'password123', 'passw0rd',
        '12345678', '123456789', '1234567890', '12341234', '87654321',
        '11111111', '00000000', '88888888', '12312312',
        'qwertyui', 'qwerty123', 'asdfghjk', '1q2w3e4r', 'qweasdzxc',
        'abc12345', 'abcd1234', 'a1234567',
        'iloveyou', 'sayangku', 'rahasia1', 'rahasia123',
        'indonesia', 'sekolahku', 'sekolah123', 'admin123', 'administrator',
    ];

    /**
     * @param  list<?string>  $tambahan  Nilai dari formulir yang sama yang tidak boleh dipakai sebagai sandi.
     */
    public function __construct(
        private readonly array $tambahan = [],
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $sandi = mb_strtolower(trim($value));

        if (in_array($sandi, self::DAFTAR_TOLAK, true)) {
            $fail('Kata sandi itu terlalu umum dan mudah ditebak. Pilih yang lain.');

            return;
        }

        foreach ($this->tambahan as $terlarang) {
            if ($terlarang !== null && $terlarang !== '' && mb_strtolower(trim($terlarang)) === $sandi) {
                $fail('Kata sandi tidak boleh sama dengan email atau nomor WhatsApp Anda.');

                return;
            }
        }
    }
}
