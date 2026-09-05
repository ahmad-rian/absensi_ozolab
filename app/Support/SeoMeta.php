<?php

namespace App\Support;

/**
 * Meta pencarian yang dirender SERVER, bukan oleh React.
 *
 * Dua alasan, dan yang kedua yang sering terlewat:
 *
 * 1. Judul saja tidak cukup. Tanpa <link rel="canonical">, dua host yang
 *    menyajikan isi sama bersaing di indeks Google dan yang menang belum tentu
 *    host yang benar — persis yang terjadi pada situs ini.
 *
 * 2. Perayap AI (GPTBot, ClaudeBot, PerplexityBot, dan kawan-kawan) umumnya
 *    TIDAK menjalankan JavaScript. Halaman Inertia tanpa SSR tampak kosong bagi
 *    mereka: <Head> milik React baru terisi setelah bundel dijalankan. Meta dan
 *    JSON-LD di Blade adalah satu-satunya yang mereka lihat.
 */
class SeoMeta
{
    /**
     * @param  array<string, mixed>  $props
     * @return array{title: string, description: string, canonical: string, robots: string, indexable: bool, jsonLd: array<int, array<string, mixed>>}
     */
    public static function forComponent(string $component, array $props = []): array
    {
        $indexable = config('seo.indexable.'.$component);
        $bolehIndeks = is_array($indexable);

        $title = $indexable['title'] ?? config('seo.default.title');
        $description = $indexable['description'] ?? config('seo.default.description');

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => self::url($indexable['path'] ?? null),
            // Halaman bertoken tidak boleh diindeks DAN tidak boleh diikuti:
            // satu tautan yang terlanjur terayapi cukup untuk membocorkan token
            // sekolah ke hasil pencarian.
            'robots' => $bolehIndeks
                ? 'index, follow, max-image-preview:large, max-snippet:-1'
                : 'noindex, nofollow',
            'indexable' => $bolehIndeks,
            'jsonLd' => $bolehIndeks ? self::jsonLd($component, $title, $description) : [],
        ];
    }

    /**
     * Alamat absolut di host kanonik.
     *
     * Sengaja TIDAK memakai url()->current(): kalau host yang menyajikan
     * permintaan memang salah, canonical-nya ikut salah — dan itu keadaan yang
     * mau diperbaiki, bukan diabadikan.
     */
    public static function url(?string $path = null): string
    {
        $base = rtrim((string) config('seo.canonical_url'), '/');

        if ($path === null || $path === '' || $path === '/') {
            return $base.'/';
        }

        return $base.'/'.ltrim($path, '/');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function jsonLd(string $component, string $title, string $description): array
    {
        $situs = config('seo.site_name');
        $organisasi = config('seo.organization');

        $graf = [
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $situs,
                'url' => self::url(),
                'inLanguage' => 'id-ID',
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => $organisasi['name'],
                    'url' => $organisasi['url'],
                ],
            ],
        ];

        if ($component !== 'welcome') {
            return $graf;
        }

        // Halaman utama saja yang membawa skema produk dan FAQ. Menempelkannya
        // di setiap halaman membuat mesin pencari melihat klaim yang sama
        // berulang-ulang di alamat berbeda.
        $graf[] = [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => $situs,
            'url' => self::url(),
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'description' => $description,
            'inLanguage' => 'id-ID',
            'featureList' => [
                'Absensi siswa dengan QR Code',
                'Absensi dengan kartu RFID',
                'Notifikasi WhatsApp ke orang tua',
                'Rekap kehadiran harian dan bulanan',
                'Kartu pelajar dan pas foto siap cetak',
                'Absen sholat dan kunjungan perpustakaan',
                'Banyak sekolah dalam satu platform',
            ],
            'provider' => [
                '@type' => 'Organization',
                'name' => $organisasi['name'],
                'url' => $organisasi['url'],
                'areaServed' => $organisasi['area'],
            ],
        ];

        $faq = collect(config('seo.faq', []))
            ->map(fn (array $item): array => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
            ])
            ->all();

        if ($faq !== []) {
            $graf[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $faq,
            ];
        }

        return $graf;
    }
}
