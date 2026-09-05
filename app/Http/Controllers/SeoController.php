<?php

namespace App\Http\Controllers;

use App\Support\SeoMeta;
use Illuminate\Http\Response;

/**
 * Berkas yang dibaca mesin: sitemap untuk mesin pencari, llms.txt untuk model
 * bahasa.
 *
 * Keduanya menyebut alamat kanonik dari konfigurasi, bukan host permintaan —
 * sitemap yang menyebut host salah justru menguatkan indeks yang salah.
 */
class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $halaman = collect(config('seo.indexable'))
            ->map(fn (array $meta): string => SeoMeta::url($meta['path'] ?? '/'))
            ->unique()
            ->values();

        // Deklarasi XML ditempel di sini, bukan di dalam view: Blade membaca
        // `<?xml` sebagai tag pembuka PHP dan berhenti mengompilasi barisnya.
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .view('seo.sitemap', ['halaman' => $halaman])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * llms.txt — ringkasan situs untuk perayap model bahasa.
     *
     * Perayap AI umumnya tidak menjalankan JavaScript, jadi halaman Inertia
     * tampak kosong bagi mereka. Berkas ini yang menjelaskan situs ini apa,
     * dalam teks biasa, tanpa perlu merender apa pun.
     */
    public function llms(): Response
    {
        $isi = view('seo.llms', [
            'situs' => config('seo.site_name'),
            'deskripsi' => config('seo.default.description'),
            'beranda' => SeoMeta::url(),
            'daftar' => SeoMeta::url('/daftar'),
            'organisasi' => config('seo.organization'),
            'faq' => config('seo.faq', []),
        ])->render();

        return response($isi, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
