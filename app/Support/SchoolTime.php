<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeInterface;

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

    /**
     * Timestamp untuk dibaca manusia, dalam jam sekolah.
     *
     * Kolom `created_at`/`updated_at` diisi Eloquent memakai `config('app.timezone')`
     * yang di sini UTC, jadi memformatnya langsung — `$log->created_at->format(...)`
     * — menampilkan angka tujuh jam lebih awal. Kartu yang dibuat pukul 08.41 WIB
     * muncul sebagai "01:41", dan itulah yang dikeluhkan operator.
     *
     * Konversi dikerjakan saat tampil, bukan dengan mengubah `app.timezone`:
     * mengubah konfigurasinya tidak menyentuh baris lama sama sekali (nilai yang
     * sudah tersimpan tetap dibaca apa adanya) dan akan menggeser absensi, yang
     * sengaja ditulis sebagai jam dinding lokal — lihat catatan kelas ini.
     *
     * Bukan untuk kolom absensi. Kolom itu sudah lokal sejak ditulis; melewatkannya
     * ke sini akan menambahkan tujuh jam kedua kalinya.
     */
    public static function display(?DateTimeInterface $time, string $format = 'd M Y H:i'): ?string
    {
        // DateTimeInterface, bukan Carbon: atribut model datang sebagai
        // CarbonImmutable (lihat AppServiceProvider), yang bukan turunan Carbon.
        return $time
            ? CarbonImmutable::instance($time)->setTimezone(self::timezone())->format($format)
            : null;
    }
}
