<?php

use App\Support\JamDinding;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

test('menit dibaca dari digit jam dinding, tanpa konversi zona', function () {
    // Atribut model datang sebagai CarbonImmutable ber-zona UTC yang digitnya
    // sudah jam Jakarta. Mengonversinya ke Asia/Jakarta menambah 7 jam.
    expect(JamDinding::menit(CarbonImmutable::parse('2026-10-06 07:26:00', 'UTC')))->toBe(7 * 60 + 26)
        ->and(JamDinding::menit(Carbon::parse('2026-10-06 06:05:00', 'Asia/Jakarta')))->toBe(6 * 60 + 5)
        ->and(JamDinding::menit('2026-10-06 07:26:59'))->toBe(7 * 60 + 26)
        ->and(JamDinding::menit('2026-10-06T12:10:00.000000Z'))->toBe(12 * 60 + 10)
        ->and(JamDinding::menit(null))->toBeNull()
        ->and(JamDinding::menit(''))->toBeNull()
        ->and(JamDinding::menit('bukan jam'))->toBeNull();
});

test('rata-rata jam dihitung dari menit, bukan dari angka jam desimal', function () {
    // 06:50 dan 07:20 rata-ratanya 07:05. Rata-rata "jam.menit" sebagai
    // desimal (6.50 dan 7.20) memberi 6.85 yang lalu terbaca "06.85".
    expect(JamDinding::rataRata(['2026-10-06 06:50:00', '2026-10-07 07:20:00']))->toBe('07:05')
        ->and(JamDinding::rataRata([CarbonImmutable::parse('2026-10-06 07:24:00', 'UTC'), null]))->toBe('07:24')
        ->and(JamDinding::rataRata([]))->toBeNull()
        ->and(JamDinding::rataRata([null]))->toBeNull();
});

test('format selalu HH:MM dengan titik dua dan dibulatkan ke menit terdekat', function () {
    expect(JamDinding::format(7 * 60 + 24))->toBe('07:24')
        ->and(JamDinding::format(375.5))->toBe('06:16')
        ->and(JamDinding::format(375.4))->toBe('06:15')
        ->and(JamDinding::format(0))->toBe('00:00')
        ->and(JamDinding::format(null))->toBeNull();
});
