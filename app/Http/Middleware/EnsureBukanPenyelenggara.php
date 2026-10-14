<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun penyelenggara tidak boleh menyentuh area admin sekolah.
 *
 * Global scope BelongsToSchool tidak memfilter apa pun untuk user tanpa
 * `school_id` (fail-open), sehingga akun penyelenggara yang lolos permission
 * akan melihat data semua sekolah. Ditolak di pintu grup `/admin`.
 */
class EnsureBukanPenyelenggara
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isPenyelenggaraUser()) {
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                abort(403);
            }

            return to_route('penyelenggara.dashboard');
        }

        return $next($request);
    }
}
