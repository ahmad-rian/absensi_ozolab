<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
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
            'password' => ['required', 'string', 'confirmed', self::aturan($request), 'not_in:password,11111111'],
        ], [
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.mixed' => 'Kata sandi harus memuat huruf besar dan huruf kecil.',
            'password.numbers' => 'Kata sandi harus memuat setidaknya satu angka.',
            'password.not_in' => 'Kata sandi itu terlalu umum. Pilih yang lain.',
        ]);

        $request->user()->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => false, 'remember_token' => null])->save();
        $request->session()->regenerate();

        return to_route($request->user()->homeRoute());
    }

    /**
     * Aturan kekuatan sandi, dibedakan per audiens.
     *
     * Orang tua memakai aturan yang sama persis dengan yang dipakai saat
     * sandinya dibuat di /daftar, dan halaman ini menampilkannya sebagai daftar
     * centang. Kalau keduanya boleh berbeda, daftar centang itu berbohong:
     * `Password::defaults()` di produksi menuntut 12 karakter plus simbol plus
     * pemeriksaan kebocoran, jadi orang tua bisa mencentang semuanya dan tetap
     * ditolak tanpa tahu apa yang kurang.
     *
     * Peran lain — admin, staf, superadmin — tetap pada `Password::defaults()`
     * yang lebih ketat. Akun mereka memegang data seluruh sekolah, bukan satu
     * anak, dan halaman ini tidak menjanjikan apa pun kepada mereka.
     */
    private static function aturan(Request $request): Password
    {
        return $request->user()->hasRole(UserRole::OrangTua->value)
            ? Password::min(8)->mixedCase()->numbers()
            : Password::defaults();
    }
}
