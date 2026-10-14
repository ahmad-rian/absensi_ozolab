<?php

namespace App\Http\Controllers;

use App\Models\PenyelenggaraInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman publik penerimaan undangan akun penyelenggara.
 */
class UndanganController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = PenyelenggaraInvitation::findByToken($token);
        $valid = $invitation?->isUsable() ?? false;

        return Inertia::render('undangan/show', [
            'token' => $token,
            'valid' => $valid,
            'email' => $valid ? $invitation->email : null,
            'role' => $valid ? $invitation->roleEnum()->label() : null,
            'penyelenggara' => $valid ? $invitation->penyelenggara()->first()?->name : null,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = PenyelenggaraInvitation::findByToken($token);

        abort_unless($invitation?->isUsable(), 410, 'Undangan tidak berlaku. Minta admin penyelenggara mengirim undangan baru.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        abort_if(User::where('email', $invitation->email)->exists(), 409, 'Email ini sudah terdaftar. Silakan masuk lewat halaman login.');

        $user = DB::transaction(function () use ($invitation, $validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $invitation->email,
                'password' => $validated['password'],
                'penyelenggara_id' => $invitation->penyelenggara_id,
                'is_active' => true,
                'must_change_password' => false,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole($invitation->role);

            $invitation->forceFill(['accepted_at' => now()])->save();

            activity()
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties(['role' => $invitation->role, 'penyelenggara_id' => $invitation->penyelenggara_id])
                ->log('undangan diterima');

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('penyelenggara.dashboard');
    }
}
