<?php

use App\Enums\SchoolFeature;
use App\Models\School;

/**
 * Halaman scan versi ringan untuk perangkat gerbang berspesifikasi rendah.
 *
 * Alasan keberadaannya: resources/css/app.css memakai oklch(), yang baru dikenal
 * Chrome 111+, sedangkan box Android TV di gerbang umumnya masih Chrome 80-100
 * dan gagal mem-parse seluruh variabel warnanya. Penjaga tingkat sumber di bawah
 * yang mencegah halaman ini diam-diam kembali berat.
 */
test('halaman ringan tampil dan menyebut nama sekolahnya', function () {
    $school = School::factory()->create(['name' => 'SMP MAJU MAKMUR']);

    $this->get(route('public.scanner.light', ['school' => $school->scanner_token]))
        ->assertOk()
        ->assertSee('SMP MAJU MAKMUR')
        ->assertSee('csrf-token', false);
});

test('sekolah nonaktif menampilkan pesan, bukan 403', function () {
    $school = School::factory()->create(['is_active' => false]);

    $this->get(route('public.scanner.light', ['school' => $school->scanner_token]))
        ->assertOk()
        ->assertSee('sedang tidak aktif');
});

test('fitur absensi yang dimatikan admin dibedakan dari sekolah nonaktif', function () {
    $school = School::factory()->create();
    $school->setSetting(SchoolFeature::AbsensiSekolah->settingKey(), false);

    $this->get(route('public.scanner.light', ['school' => $school->scanner_token]))
        ->assertOk()
        ->assertSee('dimatikan oleh admin');
});

test('token pemindai yang tidak dikenal tetap 404', function () {
    $this->get('/scan/token-karangan/ringan')->assertNotFound();
});

test('alamat pendek /g/{kode} mengarahkan ke halaman ringan sekolahnya', function () {
    $school = School::factory()->create();
    $kode = substr($school->scanner_token, 0, 8);

    $this->get('/g/'.$kode)
        ->assertRedirect(route('public.scanner.light', ['school' => $school->scanner_token]));
});

test('kode yang salah panjang atau tidak dikenal dijawab 404', function () {
    School::factory()->create();

    $this->get('/g/abcdefgh')->assertNotFound();
    $this->get('/g/abc')->assertNotFound();
});

/**
 * `_` dan `%` adalah wildcard LIKE. Tanpa penjaga bentuk, `/g/________` cocok
 * dengan token sekolah mana pun dan pemakainya mendarat di gerbang orang lain.
 */
test('wildcard LIKE tidak bisa dipakai menebak sekolah', function () {
    School::factory()->create();

    $this->get('/g/________')->assertNotFound();
    $this->get('/g/%%%%%%%%')->assertNotFound();
});

test('kode yang ambigu ditolak, bukan ditebak', function () {
    // Dua sekolah dengan awalan token sama — tidak mungkin dipilih dengan benar.
    School::factory()->create(['scanner_token' => 'KEMBAR11'.str_repeat('a', 32)]);
    School::factory()->create(['scanner_token' => 'KEMBAR11'.str_repeat('b', 32)]);

    $this->get('/g/KEMBAR11')->assertNotFound();
});

/**
 * Penjaga tingkat sumber. Ketiga hal ini yang membuat aplikasi utama tidak bisa
 * dibuka di perangkat sasaran; halaman ringan tidak boleh memungutnya kembali.
 */
test('halaman ringan tidak memungut kembali beban yang justru dihindarinya', function () {
    // Komentar Blade dibuang dulu: catatan di kepala berkas menyebut ketiga
    // istilah ini justru untuk menjelaskan kenapa mereka dilarang.
    $blade = preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents(resource_path('views/scan/light.blade.php')));

    expect($blade)
        ->not->toContain('@vite')
        ->not->toContain('oklch(')
        ->not->toContain('color-mix(')
        ->not->toContain('aspect-ratio');
});
