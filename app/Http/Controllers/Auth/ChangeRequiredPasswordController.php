<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Rules\SandiUmum;
use App\Support\AturanSandi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ChangeRequiredPasswordController extends Controller
{
    public function edit(Request $request): Response
    {
        $orangTua = self::orangTua($request);

        return Inertia::render('auth/ganti-password', [
            'orangTua' => $orangTua,
            // Syaratnya dibangkitkan dari aturan yang sama dengan yang menolak,
            // jadi daftar centang di halaman tidak bisa menjanjikan sesuatu
            // yang validatornya tidak setujui.
            'sandi' => AturanSandi::deskripsi(ketat: false),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $minimal = AturanSandi::MIN_LONGGAR;

        $data = $request->validate([
            'password' => ['required', 'string', 'confirmed', ...self::aturan(), 'not_in:password,11111111'],
        ], [
            'password.min' => "Kata sandi minimal {$minimal} karakter.",
            'password.mixed' => 'Kata sandi harus memuat huruf besar dan huruf kecil.',
            'password.letters' => 'Kata sandi harus memuat setidaknya satu huruf.',
            'password.numbers' => 'Kata sandi harus memuat setidaknya satu angka.',
            'password.symbols' => 'Kata sandi harus memuat setidaknya satu simbol, misalnya ! @ #.',
            'password.uncompromised' => 'Kata sandi ini pernah bocor di internet. Pilih yang lain.',
            'password.not_in' => 'Kata sandi itu terlalu umum. Pilih yang lain.',
        ]);

        $request->user()->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => false, 'remember_token' => null])->save();
        $request->session()->regenerate();

        return to_route($request->user()->homeRoute());
    }

    private static function orangTua(Request $request): bool
    {
        return $request->user()->hasRole(UserRole::OrangTua->value);
    }

    /**
     * Semua peran di halaman ini memakai ambang yang sama: delapan karakter
     * plus daftar-tolak SandiUmum.
     *
     * Sempat dibedakan — admin dan staf dikenai Password::defaults() yang di
     * produksi menuntut 12 karakter, huruf besar-kecil, angka, simbol, dan cek
     * kebocoran. Dilonggarkan atas keputusan pemilik aplikasi (24 Sep 2026):
     * halaman ini muncul pada login pertama akun yang dibuatkan admin, dan
     * empat syarat komposisi di depan staf sekolah terbukti terlalu sulit.
     * Pintu login tetap dibatasi 5 percobaan per menit per email+IP.
     *
     * Hanya halaman ini. Password::defaults() — dipakai ganti sandi di
     * pengaturan profil dan reset sandi — tidak disentuh.
     *
     * @return list<Password|SandiUmum>
     */
    private static function aturan(): array
    {
        return [AturanSandi::longgar(), new SandiUmum];
    }
}
