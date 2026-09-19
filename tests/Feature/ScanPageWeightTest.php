<?php

/*
 | Bobot halaman gerbang, dijaga di tingkat sumber.
 |
 | Tiga gerbang publik memakai satu konsol React yang sama, dan semuanya dibuka
 | di perangkat yang paling lemah di sekolah. Yang mudah hilang tanpa disadari
 | ada tiga: pustaka kamera kembali diimpor statis, halaman baru lupa
 | didaftarkan sehingga merender kerangka sidebar admin, dan pas foto dikirim
 | dalam ukuran penuh.
 |
 | Ketiganya pernah benar-benar terjadi. Berkas ini menutup ketiganya.
 */

// ------------------------------------------------- pustaka kamera 360 KB

test('konsol scan tidak mengimpor pustaka kamera secara statis', function () {
    $sumber = file_get_contents(resource_path('js/components/scanner/public-scan-console.tsx'));

    /*
        `html5-qrcode` membawa dekoder ZXing dan sendirian ±360 KB. Selama ia
        diimpor statis, chunk itu ikut diunduh di SETIAP gerbang — termasuk PC
        bersenjata barcode gun yang tidak punya kamera sama sekali.

        Yang diizinkan hanya impor TIPE (terhapus saat build) dan `import()`
        dinamis di dalam jalur kamera.
    */
    expect($sumber)
        ->not->toContain("import { Html5Qrcode } from 'html5-qrcode'")
        ->toContain("import type { Html5Qrcode } from 'html5-qrcode'")
        ->toContain("import('html5-qrcode')");
});

test('pustaka kamera digerbangi pemeriksaan perangkat bawaan peramban', function () {
    $sumber = file_get_contents(resource_path('js/components/scanner/public-scan-console.tsx'));

    // `enumerateDevices()` mengisi `kind` bahkan sebelum izin diberikan, jadi
    // keberadaan kamera bisa diketahui tanpa memuat pustakanya lebih dulu.
    expect($sumber)
        ->toContain('enumerateDevices')
        ->toContain("d.kind === 'videoinput'");
});

// ------------------------------------------- halaman publik vs layout admin

test('setiap halaman scan terdaftar sebagai halaman tanpa layout admin', function () {
    $app = file_get_contents(resource_path('js/app.tsx'));

    $halaman = collect(glob(resource_path('js/pages/scan/*.tsx')))
        ->map(fn (string $path) => 'scan/'.pathinfo($path, PATHINFO_FILENAME));

    expect($halaman)->not->toBeEmpty();

    /*
        `app.tsx` menyebut halaman publik satu per satu; yang tidak disebut
        jatuh ke `default: AppLayout`. `scan/prayer` dan `scan/library` pernah
        terlewat persis begitu — dua tautan publik yang merender kerangka
        sidebar admin lengkap dengan menu internalnya.
    */
    foreach ($halaman as $nama) {
        expect($app)->toContain("name === '{$nama}'");
    }
});

// ------------------------------------------------------ pas foto di respons

/*
 | Penjaga tingkat sumber, dan sengaja HANYA itu.
 |
 | Perilaku runtime-nya — thumbnail dipakai kalau ada, foto asli kalau belum —
 | sudah diuji lewat HTTP di GatePerformanceTest pada jalur absensi sekolah.
 | Menirunya untuk sholat dan perpustakaan menuntut menyiapkan jendela waktu
 | sholat dan aturan kunjungan perpustakaan, dan percobaan pertama justru
 | menghasilkan tes yang lulus tanpa menegaskan apa pun karena endpoint-nya
 | menolak lebih dulu. Tes semacam itu lebih buruk daripada tidak ada.
 |
 | Yang benar-benar perlu dijaga cuma satu: tidak ada satu pun dari ketiga
 | controller yang kembali menyusun URL foto sendiri.
 */
test('ketiga controller scan memakai helper foto yang sama', function () {
    foreach (['PublicScannerController', 'PrayerScannerController', 'LibraryScannerController'] as $kelas) {
        $sumber = file_get_contents(app_path("Http/Controllers/{$kelas}.php"));

        expect($sumber)
            ->toContain('StudentPhotoStorage::displayUrl($student->photo_path)')
            ->not->toContain("Storage::disk('public')->url(\$student->photo_path)");
    }
});
