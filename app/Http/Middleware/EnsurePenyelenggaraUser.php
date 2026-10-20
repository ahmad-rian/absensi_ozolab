<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pintu area `/penyelenggara`: hanya super admin atau akun penyelenggara.
 *
 * Akun sekolah dan orang tua ditolak 403. Penyelenggara nonaktif memblokir
 * akunnya sendiri (super admin tetap bisa masuk untuk membantu).
 */
class EnsurePenyelenggaraUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && ($user->bolehLintasPenyelenggara() || $user->isPenyelenggaraUser()), 403);

        if (! $user->bolehLintasPenyelenggara()) {
            $penyelenggara = $user->penyelenggara;

            if (! $penyelenggara || ! $penyelenggara->is_active || ! $user->is_active) {
                return Inertia::render('penyelenggara/nonaktif', [
                    'name' => $penyelenggara?->name,
                ])->toResponse($request)->setStatusCode(403);
            }
        }

        return $next($request);
    }
}
