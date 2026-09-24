<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Rules\SandiUmum;
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
        return Inertia::render('auth/ganti-password', [
            'orangTua' => $request->user()->hasRole(UserRole::OrangTua->value),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'confirmed', ...self::aturan($request), 'not_in:password,11111111'],
        ], [
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.not_in' => 'Kata sandi itu terlalu umum. Pilih yang lain.',
        ]);

        $request->user()->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => false, 'remember_token' => null])->save();
        $request->session()->regenerate();

        return to_route($request->user()->homeRoute());
    }

    /**
     * Aturan kekuatan sandi, dibedakan per audiens.
     *
     * Orang tua memakai aturan yang sama persis dengan saat sandinya dibuat di
     * /daftar: delapan karakter tanpa aturan komposisi, ditambah daftar-tolak
     * `SandiUmum`. Kalau keduanya boleh berbeda, halaman ini menolak sandi yang
     * kemarin diterima halaman pendaftaran, dan pemiliknya tidak punya cara
     * menebak apa yang berubah — di produksi `Password::defaults()` menuntut 12
     * karakter plus simbol plus pemeriksaan kebocoran.
     *
     * Peran lain — admin, staf, superadmin — tetap pada `Password::defaults()`
     * yang lebih ketat. Akun mereka memegang data seluruh sekolah, bukan satu
     * anak, dan tidak diisi oleh orang yang baru pertama kali membuka portal.
     *
     * @return list<Password|SandiUmum>
     */
    private static function aturan(Request $request): array
    {
        return $request->user()->hasRole(UserRole::OrangTua->value)
            ? [Password::min(8), new SandiUmum]
            : [Password::defaults()];
    }
}
