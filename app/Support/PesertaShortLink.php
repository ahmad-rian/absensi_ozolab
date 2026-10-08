<?php

namespace App\Support;

use App\Models\CardForm;

/**
 * Alamat pendek halaman scan ringan peserta (haji/umrah): `/p/{kode}`.
 *
 * Sama alasannya dengan {@see ScannerShortLink}: alamat diketik dengan remote
 * box TV, dan token scanner 48 karakter tidak boleh tampil di HTML halaman
 * yang alamatnya sengaja gampang diketik. Kodenya 8 karakter pertama token,
 * jadi memutar ulang link scan otomatis mematikan alamat pendeknya juga.
 */
class PesertaShortLink
{
    public const PANJANG = 8;

    public static function codeFor(CardForm $form): string
    {
        return substr((string) $form->scanner_token, 0, self::PANJANG);
    }

    /** Layout pemilik kode, atau null kalau tidak ada / ambigu. */
    public static function resolve(string $kode): ?CardForm
    {
        // Bentuk dikunci dulu: `_` dan `%` adalah wildcard LIKE.
        if (strlen($kode) !== self::PANJANG || ! ctype_alnum($kode)) {
            return null;
        }

        $cocok = CardForm::where('scanner_token', 'like', $kode.'%')->limit(2)->get();

        return $cocok->count() === 1 ? $cocok->first() : null;
    }
}
