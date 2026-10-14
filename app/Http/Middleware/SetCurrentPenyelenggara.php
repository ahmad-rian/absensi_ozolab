<?php

namespace App\Http\Middleware;

use App\Models\Penyelenggara;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tetapkan penyelenggara yang sedang dibuka (`currentPenyelenggara`).
 *
 * Super admin memilihnya lewat session (per browser); akun penyelenggara
 * selalu terkunci ke `users.penyelenggara_id`. Tanpa konteks, trait
 * BelongsToPenyelenggara mengembalikan nol baris (fail-closed).
 */
class SetCurrentPenyelenggara
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Penanda area: tabel kartu bersama (CardTenancy) menjadi fail-closed
        // di sini walau konteks belum dipilih.
        app()->instance('areaPenyelenggara', true);

        // Rute scan publik sengaja berjalan tanpa session (dan tanpa CSRF);
        // di sana super admin memang tidak punya konteks penyelenggara.
        $session = $request->hasSession() ? $request->session() : null;

        $id = $user->isSuperAdmin()
            ? $session?->get('current_penyelenggara_id')
            : $user->penyelenggara_id;

        $penyelenggara = $id ? Penyelenggara::find($id) : null;

        if (! $penyelenggara) {
            $session?->forget('current_penyelenggara_id');

            return $next($request);
        }

        app()->instance('currentPenyelenggara', $penyelenggara);

        return $next($request);
    }
}
