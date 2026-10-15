<?php

namespace App\Support;

use DateTimeInterface;

/**
 * Jam dinding (jam dalam sehari) dari kolom waktu absensi, dan rata-ratanya.
 *
 * `recorded_at` absensi disimpan sebagai jam dinding sekolah (lihat
 * {@see SchoolTime}) di kolom yang dibaca Eloquent sebagai UTC. Digitnya
 * sudah jam Jakarta, jadi dibaca apa adanya: `setTimezone('Asia/Jakarta')`
 * di sini akan menambah tujuh jam kedua kalinya.
 *
 * Rata-rata dihitung dari menit sejak tengah malam, tidak pernah dari angka
 * "jam.menit" sebagai desimal (06.50 dan 07.20 bukan 6,85) dan tidak dari
 * timestamp utuh (yang ikut membawa tanggal). Satu-satunya tempat aturan ini
 * hidup: dashboard, detail siswa, dan laporan memakai kelas yang sama.
 */
final class JamDinding
{
    /**
     * Menit sejak tengah malam, atau null bila nilainya tidak berisi jam.
     */
    public static function menit(DateTimeInterface|string|null $waktu): ?int
    {
        if ($waktu instanceof DateTimeInterface) {
            return ((int) $waktu->format('G')) * 60 + (int) $waktu->format('i');
        }

        if ($waktu === null || preg_match('/(?:^|[\sT])(\d{1,2}):(\d{2})/', $waktu, $cocok) !== 1) {
            return null;
        }

        return ((int) $cocok[1]) * 60 + (int) $cocok[2];
    }

    /**
     * Rata-rata jam dari sekumpulan waktu, dalam format "HH:MM".
     *
     * @param  iterable<DateTimeInterface|string|null>  $waktu
     */
    public static function rataRata(iterable $waktu): ?string
    {
        return self::format(self::rataRataMenit($waktu));
    }

    /**
     * Rata-rata menit tanpa pembulatan — dipakai untuk mengurutkan, supaya
     * urutan dan angka yang tampil berasal dari nilai yang sama.
     *
     * @param  iterable<DateTimeInterface|string|null>  $waktu
     */
    public static function rataRataMenit(iterable $waktu): ?float
    {
        $menit = [];

        foreach ($waktu as $nilai) {
            $hasil = self::menit($nilai);

            if ($hasil !== null) {
                $menit[] = $hasil;
            }
        }

        return $menit === [] ? null : array_sum($menit) / count($menit);
    }

    /**
     * Menit sejak tengah malam menjadi "HH:MM", dibulatkan ke menit terdekat.
     */
    public static function format(int|float|null $menit): ?string
    {
        if ($menit === null) {
            return null;
        }

        $bulat = (int) round($menit);

        return sprintf('%02d:%02d', intdiv($bulat, 60), $bulat % 60);
    }
}
