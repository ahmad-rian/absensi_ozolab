<?php

namespace App\Support;

use RuntimeException;
use Spatie\Browsershot\Browsershot;

/**
 * Pasang biner Chrome ke Browsershot.
 *
 * Sebelumnya penjaganya disalin di lima service dan berbunyi
 * `if ($path && file_exists($path))` — artinya `CHROME_PATH` yang terisi tapi
 * berkasnya sudah tidak ada akan **dilewati tanpa suara**, dan Browsershot
 * jatuh ke pencarian Chrome bawaannya lalu gagal dengan galat Node sepanjang
 * layar yang tidak menyebut Chrome sama sekali.
 *
 * Itu terjadi sungguhan pada 1 September 2026: direktori Chrome ikut tersapu
 * saat bersih-bersih server, ~1425 kartu gagal dirender sepanjang sore di 16
 * sekolah, dan tidak ada satu pun pesan yang menunjuk penyebabnya. Sekarang
 * keadaan itu gagal dengan kalimat yang bisa langsung ditindaklanjuti.
 */
class ChromeBinary
{
    public static function applyTo(Browsershot $browsershot): void
    {
        $path = config('services.chrome.path');

        // Tidak diatur sama sekali itu sah — mesin pengembang mengandalkan
        // Chrome yang sudah ada di PATH. Yang tidak sah adalah diatur ke
        // sesuatu yang tidak ada.
        if (! $path) {
            return;
        }

        if (! file_exists($path)) {
            throw new RuntimeException(
                "Chrome tidak ditemukan di CHROME_PATH: {$path}. "
                .'Pasang ulang Chrome, perbarui CHROME_PATH di .env, lalu jalankan `php artisan optimize` dan restart worker.'
            );
        }

        $browsershot->setChromePath($path);
    }
}
