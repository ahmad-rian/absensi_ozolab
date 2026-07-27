<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Waktu operasional sekolah.
 *
 * `config('app.timezone')` sengaja dibiarkan UTC agar timestamp lama tidak
 * bergeser, sementara seluruh absensi ditulis & dibaca dengan jam dinding
 * lokal sekolah. Semua kode absensi harus lewat helper ini, jangan memanggil
 * `now()` / `Carbon::today()` langsung.
 */
class SchoolTime
{
    public static function timezone(): string
    {
        return config('attendance.timezone', 'Asia/Jakarta');
    }

    public static function now(): Carbon
    {
        return Carbon::now(self::timezone());
    }

    public static function today(): Carbon
    {
        return self::now()->startOfDay();
    }

    public static function todayString(): string
    {
        return self::now()->toDateString();
    }

    /**
     * Pindahkan sebuah waktu ke timezone sekolah tanpa memutasi instance asal.
     */
    public static function toLocal(Carbon $time): Carbon
    {
        return $time->copy()->setTimezone(self::timezone());
    }
}
