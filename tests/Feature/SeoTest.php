<?php

use App\Models\School;
use App\Support\SeoMeta;

/**
 * Hasil pencarian menampilkan judul dan deskripsi situs ini di bawah domain yang
 * bukan miliknya. Penyebabnya: isi yang sama pernah tersaji di host lain, dan
 * aplikasi tidak pernah punya <link rel="canonical"> yang memberi tahu Google
 * host mana yang sah.
 */
beforeEach(function () {
    config()->set('seo.canonical_url', 'https://tyasphoto.ozolab.id');
});

test('beranda menyebut alamat kanonik, bukan host yang menyajikannya', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="https://tyasphoto.ozolab.id/">', false)
        ->assertSee('<meta property="og:url" content="https://tyasphoto.ozolab.id/">', false);
});

/**
 * Inti perbaikannya. Kalau canonical ikut host permintaan, host yang salah
 * menerbitkan canonical yang salah juga — indeks yang keliru justru dikuatkan.
 */
test('canonical tidak ikut berubah walau diakses dari host lain', function () {
    $this->get('http://jadikayagroup.site/')
        ->assertOk()
        ->assertSee('href="https://tyasphoto.ozolab.id/"', false)
        ->assertDontSee('href="http://jadikayagroup.site/"', false);
});

test('beranda punya judul, deskripsi, dan izin indeks', function () {
    $respons = $this->get('/')->assertOk();

    $respons->assertSee('<title>Tyas Photo — Absensi Sekolah Digital dengan QR &amp; RFID</title>', false);
    $respons->assertSee('name="description"', false);
    $respons->assertSee('index, follow', false);
});

/**
 * Perayap AI umumnya tidak menjalankan JavaScript. Tanpa ini halaman Inertia
 * tampak kosong bagi mereka.
 */
test('data terstruktur dirender server, bukan menunggu React', function () {
    $respons = $this->get('/')->assertOk();

    $respons->assertSee('application/ld+json', false);
    $respons->assertSee('"@type":"SoftwareApplication"', false);
    $respons->assertSee('"@type":"FAQPage"', false);
    // Pertanyaan FAQ ikut terkirim mentah, jadi ringkasan AI bisa membacanya
    // tanpa merender apa pun.
    $respons->assertSee('Apakah perlu hardware khusus untuk scan QR?', false);
});

test('halaman bertoken tidak boleh diindeks', function () {
    $school = School::factory()->create();

    $this->get(route('public.scanner.light', ['school' => $school->scanner_token]))
        ->assertOk()
        ->assertSee('noindex, nofollow', false);

    $this->get(route('public.scanner', ['school' => $school->scanner_token]))
        ->assertOk()
        ->assertSee('noindex, nofollow', false);
});

test('halaman yang tidak ada di daftar putih otomatis noindex', function () {
    $meta = SeoMeta::forComponent('admin/schools/index');

    expect($meta['indexable'])->toBeFalse()
        ->and($meta['robots'])->toBe('noindex, nofollow')
        ->and($meta['jsonLd'])->toBe([]);
});

test('sitemap memuat halaman publik di host kanonik', function () {
    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('https://tyasphoto.ozolab.id/', false)
        ->assertSee('https://tyasphoto.ozolab.id/daftar', false)
        // Halaman bertoken tidak boleh bocor lewat sitemap.
        ->assertDontSee('/scan/', false);
});

test('llms.txt menjelaskan situs dalam teks biasa', function () {
    $this->get('/llms.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Tyas Photo')
        ->assertSee('Kartu RFID')
        ->assertSee('Apakah sistem ini gratis?');
});

test('robots.txt menutup jalur bertoken dan menunjuk sitemap', function () {
    $robots = file_get_contents(public_path('robots.txt'));

    expect($robots)
        ->toContain('Disallow: /scan/')
        ->toContain('Disallow: /g/')
        ->toContain('Disallow: /admin')
        ->toContain('Sitemap: https://tyasphoto.ozolab.id/sitemap.xml')
        // Perayap AI sengaja diizinkan untuk halaman publik.
        ->toContain('User-agent: GPTBot');
});
