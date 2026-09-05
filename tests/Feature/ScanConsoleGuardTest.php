<?php

/**
 * Penjaga tingkat sumber untuk konsol scan.
 *
 * Konsol dulu memasang kunci menyeluruh 1800 ms setiap habis scan, dan timernya
 * baru mulai SETELAH respons server tiba — jeda nyatanya round-trip + 1,8 detik,
 * berlaku juga untuk kartu yang berbeda. Itu yang membuat antrean di gerbang
 * tersendat. Penggantinya menahan hanya kartu yang sama.
 */
test('konsol scan tidak lagi mengunci seluruh scan setelah satu kartu', function () {
    $sumber = file_get_contents(resource_path('js/components/scanner/public-scan-console.tsx'));

    expect($sumber)
        ->not->toContain('cooldownRef')
        ->not->toContain('lastTokenRef')
        ->toContain('SAME_CARD_MS')
        ->toContain('inFlightRef');
});

/**
 * Buffer barcode gun memakai timer diam yang direset tiap tombol. Pada 150 ms,
 * satu keystroke yang tertunda saat React render membuang buffer di tengah UID —
 * sisanya terkirim sebagai token potong dan ditolak server. Itu penjelasan
 * "kadang jadi kadang tidak" di perangkat lemot.
 */
test('jeda antar-karakter cukup longgar untuk perangkat lemot', function () {
    $konsol = file_get_contents(resource_path('js/components/scanner/public-scan-console.tsx'));
    $ringan = file_get_contents(resource_path('views/scan/light.blade.php'));

    preg_match('/KEY_IDLE_MS = (\d+);/', $konsol, $a);
    preg_match('/KEY_IDLE_MS = (\d+);/', $ringan, $b);

    expect((int) ($a[1] ?? 0))->toBeGreaterThanOrEqual(600)
        ->and((int) ($b[1] ?? 0))->toBeGreaterThanOrEqual(600);
});

/**
 * Elemen input tidak punya timer dan tidak bisa terpotong; buffer bisa. Kalau
 * buffer yang dimenangkan saat keduanya terisi, satu keystroke yang tertunda di
 * perangkat lemot mengirim token separuh — dan gerbang menolak kartu yang sah.
 */
test('kotak isian jadi sumber utama, bukan sekadar yang lebih panjang', function () {
    $konsol = file_get_contents(resource_path('js/components/scanner/public-scan-console.tsx'));
    $ringan = file_get_contents(resource_path('views/scan/light.blade.php'));

    foreach ([$konsol, $ringan] as $sumber) {
        expect($sumber)
            ->toContain("dariInput !== '' ? dariInput : buffer")
            ->not->toContain('dariInput.length > buffer.length');
    }
});

/**
 * Operator memegang kartunya. "terbaca 18 karakter" padahal tokennya 35
 * memperlihatkan bacaan terpotong saat itu juga, tanpa membuka server.
 */
test('kegagalan menyebutkan berapa karakter yang terbaca', function () {
    $konsol = file_get_contents(resource_path('js/components/scanner/public-scan-console.tsx'));
    $ringan = file_get_contents(resource_path('views/scan/light.blade.php'));

    foreach ([$konsol, $ringan] as $sumber) {
        expect($sumber)->toContain('terbaca ');
    }
});

/**
 * Timer diam dulu MEMBUANG buffer. Di perangkat lemot satu keystroke yang
 * tertunda memotong UID di tengah, sisanya terkirim sebagai token cacat, dan
 * gerbang menjawab "Kartu atau QR Code tidak dikenali" untuk kartu yang sah.
 *
 * Sekarang buffer dikirim, bukan dibuang — sekaligus melayani pembaca RFID yang
 * tidak mengirim Enter sama sekali.
 */
test('buffer yang menganggur dikirim, bukan dibuang', function () {
    $konsol = file_get_contents(resource_path('js/components/scanner/public-scan-console.tsx'));
    $ringan = file_get_contents(resource_path('views/scan/light.blade.php'));

    foreach ([$konsol, $ringan] as $sumber) {
        expect($sumber)
            ->toContain('MIN_TOKEN')
            ->toContain('MACHINE_MS_PER_KEY');
    }
});

/**
 * html5-qrcode 2.3.8 melempar kalau resume() dipanggil saat state bukan PAUSED.
 * Sekali gagal, kameranya beku selamanya — dan penjedaan itu cuma penghematan
 * CPU, bukan kebutuhan.
 */
test('kamera tidak pernah dijeda-lanjutkan sendiri', function () {
    $konsol = file_get_contents(resource_path('js/components/scanner/public-scan-console.tsx'));

    expect($konsol)
        ->not->toContain('pauseCamera')
        ->not->toContain('resumeCamera');
});

test('pas foto di layar scan memakai rasio 3:4 dan tidak dipangkas', function () {
    $sumber = file_get_contents(resource_path('js/components/scanner/public-scan-console.tsx'));

    expect($sumber)
        ->toContain('aspect-[3/4]')
        ->not->toContain('size-28 shrink-0 rounded-2xl border-4 border-emerald-300 object-cover');
});
