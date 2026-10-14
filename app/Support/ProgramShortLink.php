<?php

namespace App\Support;

use App\Models\Program;

/**
 * Alamat pendek halaman scan ringan program penyelenggara: `/h/{kode}`.
 *
 * Pola sama dengan {@see PesertaShortLink} (Kartu Bebas), tetapi atas
 * `programs.scanner_token` — kedua ruang kode tidak pernah bercampur.
 */
class ProgramShortLink
{
    public const PANJANG = 8;

    public static function codeFor(Program $program): string
    {
        return substr((string) $program->scanner_token, 0, self::PANJANG);
    }

    /** Program pemilik kode, atau null kalau tidak ada / ambigu. */
    public static function resolve(string $kode): ?Program
    {
        if (strlen($kode) !== self::PANJANG || ! ctype_alnum($kode)) {
            return null;
        }

        $cocok = Program::acrossPenyelenggara()->where('scanner_token', 'like', $kode.'%')->limit(2)->get();

        return $cocok->count() === 1 ? $cocok->first() : null;
    }

    /** Program pemilik token penuh (halaman kamera). */
    public static function fromToken(string $token): ?Program
    {
        if (strlen($token) < 20 || ! ctype_alnum($token)) {
            return null;
        }

        return Program::acrossPenyelenggara()->where('scanner_token', $token)->first();
    }
}
