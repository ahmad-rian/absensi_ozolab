<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
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

test('sandi orang tua yang tidak memenuhi ketentuan ditolak dengan alasannya', function (string $sandi) {
    $user = penggunaWajibGanti('ORANG_TUA');

    $this->actingAs($user)
        ->put(route('password.required.update'), ['password' => $sandi, 'password_confirmation' => $sandi])
        ->assertSessionHasErrors('password');

    expect($user->fresh()->must_change_password)->toBeTrue();
})->with([
    'terlalu pendek' => 'Sandi1',
    'tanpa huruf besar' => 'sandiwali1',
    'tanpa angka' => 'SandiWali',
    // Sandi bawaan yang dipakai massal saat impor — persis yang halaman ini ada
    // untuk menggantikan.
    'sandi bawaan' => '11111111',
]);

test('orang tua menyimpan sandi yang memenuhi ketentuan dan halaman berhenti memaksa', function () {
    $user = penggunaWajibGanti('ORANG_TUA');

    $this->actingAs($user)
        ->put(route('password.required.update'), ['password' => 'SandiWali123', 'password_confirmation' => 'SandiWali123'])
        ->assertSessionHasNoErrors();

    $user = $user->fresh();

    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check('SandiWali123', $user->password))->toBeTrue();
});

test('aturan peran lain tidak ikut dilonggarkan atau diperketat', function () {
    // Peran non-orang-tua tetap pada Password::defaults(), yang di produksi jauh
    // lebih ketat (12 karakter, simbol, cek kebocoran). Yang diperiksa di sini:
    // percabangan peran tidak diam-diam memasang aturan orang tua pada mereka.
    $admin = penggunaWajibGanti('ADMIN');

    $this->actingAs($admin)
        ->put(route('password.required.update'), ['password' => 'sandiadmin', 'password_confirmation' => 'sandiadmin'])
        ->assertSessionHasNoErrors();

    expect($admin->fresh()->must_change_password)->toBeFalse();
});
