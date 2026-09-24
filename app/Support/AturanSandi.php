<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Satu tempat yang menentukan sekuat apa sebuah kata sandi harus dibuat.
 *
 * Ada dua ambang, dan bedanya bukan selera melainkan siapa yang mengetik:
 *
 * - `longgar()` untuk orang tua. Delapan karakter, tanpa aturan komposisi.
 *   Yang mengisi formulir pendaftaran adalah orang tua siswa; aturan komposisi
 *   pada kelompok itu menghasilkan sandi yang ditulis di kertas atau dilupakan,
 *   bukan akun yang lebih aman. Penggantinya `App\Rules\SandiUmum` dan
 *   pembatasan laju login. Lihat kelas itu untuk alasan lengkapnya.
 * - `ketat()` untuk admin, staf, dan superadmin. Akun mereka memegang data
 *   seluruh sekolah, dan mereka tidak sedang mendaftar sambil berdiri di depan
 *   meja pendaftaran.
 *
 * Kelas ini juga memasok DESKRIPSI aturannya untuk ditampilkan sebagai daftar
 * centang. Itu alasan utamanya berdiri: selama aturan dan teks yang dipajang
 * ditulis di dua tempat, keduanya pasti berbeda suatu hari, dan hasilnya
 * halaman yang menjanjikan "minimal 8 karakter" lalu menolak sandi sembilan
 * karakter tanpa memberi tahu apa yang sebenarnya kurang. Itu betul-betul
 * terjadi sebelum kelas ini ada.
 */
final class AturanSandi
{
    public const MIN_LONGGAR = 8;

    public const MIN_KETAT = 12;

    public static function longgar(): Password
    {
        return Password::min(self::MIN_LONGGAR);
    }

    public static function ketat(): Password
    {
        return Password::min(self::MIN_KETAT)
            ->mixedCase()
            ->letters()
            ->numbers()
            ->symbols()
            ->uncompromised();
    }

    /**
     * Ambang untuk peran selain orang tua.
     *
     * Di luar produksi sengaja dilonggarkan supaya tes dan pengembangan tidak
     * bergantung pada layanan pemeriksaan kebocoran yang butuh jaringan. Nilai
     * inilah yang juga dipasang sebagai `Password::defaults()`.
     */
    public static function bawaan(): Password
    {
        return app()->isProduction() ? self::ketat() : self::longgar();
    }

    /**
     * Daftar syarat untuk dipajang di halaman, seragam dengan aturan di atas.
     *
     * `kunci` dibaca komponen SyaratSandi untuk memeriksa sandi yang sedang
     * diketik. `catatan` memuat syarat yang tidak bisa diperiksa di peramban —
     * daftar-tolak dan pemeriksaan kebocoran hanya ada di server.
     *
     * @return array{syarat: list<array{kunci: string, label: string, nilai?: int}>, catatan: ?string}
     */
    public static function deskripsi(bool $ketat): array
    {
        $syarat = [[
            'kunci' => 'panjang',
            'label' => 'Minimal '.($ketat ? self::MIN_KETAT : self::MIN_LONGGAR).' karakter',
            'nilai' => $ketat ? self::MIN_KETAT : self::MIN_LONGGAR,
        ]];

        if ($ketat) {
            $syarat[] = ['kunci' => 'huruf', 'label' => 'Huruf besar dan kecil'];
            $syarat[] = ['kunci' => 'angka', 'label' => 'Ada angka'];
            $syarat[] = ['kunci' => 'simbol', 'label' => 'Ada simbol, misal ! @ #'];
        }

        return [
            'syarat' => $syarat,
            'catatan' => $ketat
                ? 'Sandi yang pernah bocor di internet akan ditolak.'
                : 'Jangan pakai yang gampang ditebak seperti 12345678.',
        ];
    }
}
