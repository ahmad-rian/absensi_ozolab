<?php

use App\Models\School;
use App\Models\User;
use App\Rules\SandiUmum;
use App\Support\AturanSandi;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 | Halaman ganti sandi wajib — yang dilihat orang tua pada login pertama.
 |
 | Sebelum ini halaman itu cuma berisi dua kolom tanpa satu kata pun tentang
 | ketentuan sandinya, sementara aturannya berbeda per peran. Yang dijaga berkas
 | ini: orang tua diberi tahu aturan yang benar-benar berlaku untuknya, dan
 | aturan peran lain tidak ikut berubah.
 */

function penggunaWajibGanti(string $peran): User
{
    $user = User::factory()->create(['must_change_password' => true]);
    $user->assignRole($peran);

    return $user;
}

test('halaman menandai orang tua supaya ketentuan sandinya bisa ditampilkan', function () {
    $this->actingAs(penggunaWajibGanti('ORANG_TUA'))
        ->get(route('password.required.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/ganti-password')->where('orangTua', true));

    $this->actingAs(penggunaWajibGanti('ADMIN'))
        ->get(route('password.required.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('orangTua', false));
});

test('syarat yang dipajang berasal dari aturan yang menolak, bukan dari teks yang ditulis terpisah', function () {
    /*
        Halaman ini pernah memajang "Kata sandi minimal 8 karakter." kepada
        akun yang sebenarnya dituntut dua belas, jadi sandi sembilan karakter
        ditolak dengan alasan yang tidak masuk akal. Yang dijaga di sini:
        angka pada daftar centang dan angka yang dipakai validator berasal dari
        sumber yang sama.
    */
    $this->actingAs(penggunaWajibGanti('ORANG_TUA'))
        ->get(route('password.required.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sandi.syarat.0.kunci', 'panjang')
            ->where('sandi.syarat.0.nilai', AturanSandi::MIN_LONGGAR)
            // Orang tua tidak dikenai aturan komposisi: yang dipajang hanya
            // panjang dan daftar-tolak, keduanya memang ditegakkan server.
            ->has('sandi.syarat', 2)
            ->where('sandi.syarat.1.kunci', 'umum')
            ->where('sandi.syarat.1.daftar', SandiUmum::DAFTAR_TOLAK));

    $panjangDitolak = str_repeat('a', AturanSandi::MIN_LONGGAR - 1);

    $this->actingAs(penggunaWajibGanti('ORANG_TUA'))
        ->put(route('password.required.update'), ['password' => $panjangDitolak, 'password_confirmation' => $panjangDitolak])
        ->assertSessionHasErrors(['password' => 'Kata sandi minimal '.AturanSandi::MIN_LONGGAR.' karakter.']);
});

test('logo sekolah muncul di halaman ganti sandi', function () {
    /*
        Rute ini berada di luar grup yang memasang SetCurrentSchool, jadi
        `currentSchool` kosong di sini. Sebelum ada fallback ke sekolah milik
        akunnya, halaman menampilkan logo Laravel bawaan padahal logonya sudah
        diunggah — logo itu tersimpan di kolom settings sekolah, bukan di tabel
        settings global.
    */
    $school = School::factory()->create(['settings' => ['app_logo' => 'images/branding/logo.webp']]);
    $user = User::factory()->create(['school_id' => $school->id, 'must_change_password' => true]);
    $user->assignRole('ORANG_TUA');

    $this->actingAs($user)
        ->get(route('password.required.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('app.logo', Storage::disk('public')->url('images/branding/logo.webp')));
});

test('sandi orang tua yang tidak memenuhi ketentuan ditolak dengan alasannya', function (string $sandi) {
    $user = penggunaWajibGanti('ORANG_TUA');

    $this->actingAs($user)
        ->put(route('password.required.update'), ['password' => $sandi, 'password_confirmation' => $sandi])
        ->assertSessionHasErrors('password');

    expect($user->fresh()->must_change_password)->toBeTrue();
})->with([
    'terlalu pendek' => 'sandi',
    // Sandi bawaan yang dipakai massal saat impor — persis yang halaman ini ada
    // untuk menggantikan.
    'sandi bawaan' => '11111111',
    'sandi umum' => '12345678',
    'sandi umum berhuruf besar' => 'Password',
]);

test('orang tua menyimpan sandi delapan huruf biasa tanpa aturan komposisi', function () {
    /*
        Ambangnya sengaja sama persis dengan /daftar. Halaman ini muncul pada
        login pertama, di depan orang yang baru saja membuat sandinya di
        halaman pendaftaran; menolak sandi yang kemarin diterima berarti
        menuntut tebakan tentang apa yang berubah.
    */
    $user = penggunaWajibGanti('ORANG_TUA');

    $this->actingAs($user)
        ->put(route('password.required.update'), ['password' => 'sandiwali', 'password_confirmation' => 'sandiwali'])
        ->assertSessionHasNoErrors();

    $user = $user->fresh();

    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check('sandiwali', $user->password))->toBeTrue();
});

test('admin dan staf memakai ambang sederhana yang sama di halaman ini', function () {
    /*
        Sempat dikenai 12 karakter + huruf besar-kecil + angka + simbol. Dinilai
        terlalu sulit oleh pemilik aplikasi; halaman ini kini satu ambang untuk
        semua peran. Daftar-tolaknya tetap berlaku untuk mereka.
    */
    $this->actingAs(penggunaWajibGanti('ADMIN'))
        ->get(route('password.required.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sandi.syarat.0.nilai', AturanSandi::MIN_LONGGAR)
            ->has('sandi.syarat', 2));

    $this->actingAs(penggunaWajibGanti('ADMIN'))
        ->put(route('password.required.update'), ['password' => '12345678', 'password_confirmation' => '12345678'])
        ->assertSessionHasErrors('password');

    $admin = penggunaWajibGanti('ADMIN');

    $this->actingAs($admin)
        ->put(route('password.required.update'), ['password' => 'sandiadmin', 'password_confirmation' => 'sandiadmin'])
        ->assertSessionHasNoErrors();

    expect($admin->fresh()->must_change_password)->toBeFalse();
});
