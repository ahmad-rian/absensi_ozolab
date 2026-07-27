<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    private const SESSION_KEY = 'impersonator_id';

    /**
     * Masuk sebagai user lain. Hanya untuk pemegang `impersonate.access`
     * (default: SUPER_ADMIN saja).
     */
    public function store(Request $request, User $user): RedirectResponse
    {
        $impersonator = $request->user();

        // Gate super-admin sebelumnya hanya ada di JSX; endpointnya bisa
        // dipanggil langsung oleh siapa pun yang memegang impersonate.access,
        // termasuk untuk user sekolah lain.
        abort_unless($impersonator->isSuperAdmin(), 403, 'Hanya Super Admin yang bisa menyamar.');
        abort_if($request->session()->has(self::SESSION_KEY), 403, 'Anda sudah dalam mode menyamar.');
        abort_if($user->is($impersonator), 403, 'Tidak bisa menyamar diri sendiri.');
        abort_if($user->isSuperAdmin(), 403, 'Tidak bisa menyamar sesama Super Admin.');
        abort_unless($user->is_active, 403, 'Akun tersebut tidak aktif.');

        activity()
            ->causedBy($impersonator)
            ->performedOn($user)
            ->withProperties(['school_id' => $user->school_id])
            ->log('impersonation.started');

        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $impersonator->id);
        $request->session()->put('current_school_id', $user->school_id);

        Auth::login($user);

        return to_route('dashboard');
    }

    /**
     * Kembali ke akun asli. Sengaja tidak butuh permission apa pun — user yang
     * sedang disamar tidak memilikinya.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->pull(self::SESSION_KEY);

        if (! $impersonatorId) {
            return to_route('dashboard');
        }

        $impersonator = User::find($impersonatorId);

        if (! $impersonator) {
            Auth::logout();
            $request->session()->invalidate();

            return to_route('login');
        }

        activity()
            ->causedBy($impersonator)
            ->performedOn($request->user())
            ->log('impersonation.stopped');

        $request->session()->regenerate();
        $request->session()->put('current_school_id', $impersonator->school_id);

        Auth::login($impersonator);

        return to_route('admin.users.index');
    }
}
