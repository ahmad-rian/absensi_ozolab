<?php

use App\Support\ChromeBinary;
use Spatie\Browsershot\Browsershot;

/**
 * Regresi 1 September 2026.
 *
 * Direktori Chrome ikut tersapu saat bersih-bersih server. `CHROME_PATH` masih
 * terisi, berkasnya sudah tidak ada, dan penjaga lama — `if ($path &&
 * file_exists($path))` yang disalin di lima service — MELEWATINYA tanpa suara.
 * Browsershot lalu jatuh ke pencarian Chrome bawaannya dan gagal dengan galat
 * Node sepanjang layar yang tidak menyebut Chrome sama sekali.
 *
 * Akibatnya ~1425 kartu gagal dirender sepanjang sore di 16 sekolah, dan butuh
 * empat jam untuk menemukan penyebabnya.
 */
test('CHROME_PATH yang menunjuk berkas tidak ada gagal dengan pesan yang menyebut jalurnya', function () {
    config(['services.chrome.path' => '/www/wwwroot/.puppeteer-cache/chrome/linux-148.0.7778.97/chrome-linux64/chrome']);

    expect(fn () => ChromeBinary::applyTo(Browsershot::html('<p>x</p>')))
        ->toThrow(
            RuntimeException::class,
            'Chrome tidak ditemukan di CHROME_PATH: /www/wwwroot/.puppeteer-cache/chrome/linux-148.0.7778.97/chrome-linux64/chrome',
        );
});

/**
 * Tidak diatur sama sekali itu sah: mesin pengembang mengandalkan Chrome yang
 * sudah ada di PATH. Kalau ini ikut melempar, seluruh suite lokal mati.
 */
test('CHROME_PATH kosong dibiarkan, Browsershot mencari Chrome sendiri', function () {
    config(['services.chrome.path' => null]);

    $browsershot = Browsershot::html('<p>x</p>');
    ChromeBinary::applyTo($browsershot);

    expect($browsershot->createScreenshotCommand('/tmp/x.png')['options'])
        ->not->toHaveKey('executablePath');
});

test('CHROME_PATH yang valid dipasang sebagai executablePath', function () {
    // Berkas apa pun yang pasti ada — yang diuji keputusannya, bukan apakah
    // binernya benar-benar Chrome.
    $adaBerkas = base_path('artisan');

    config(['services.chrome.path' => $adaBerkas]);

    $browsershot = Browsershot::html('<p>x</p>');
    ChromeBinary::applyTo($browsershot);

    expect($browsershot->createScreenshotCommand('/tmp/x.png')['options']['executablePath'])
        ->toBe($adaBerkas);
});

/**
 * Penjaga tingkat sumber: pola lama yang diam itu ada di LIMA service sekaligus.
 * Satu saja lolos kembali, kelas kegagalan yang sama hidup lagi di jalur itu.
 */
test('tidak ada lagi service yang memasang Chrome dengan penjaga diam', function () {
    $services = glob(app_path('Services/*.php'));

    foreach ($services as $file) {
        $sumber = file_get_contents($file);

        expect($sumber)->not->toContain('file_exists($chromePath)');

        if (str_contains($sumber, 'Browsershot::')) {
            expect($sumber)->toContain('ChromeBinary::applyTo(');
        }
    }
});
