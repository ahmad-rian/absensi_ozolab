<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pintu workspace Absensi General: super admin, atau staf General yang masih
 * aktif. Modul mana yang boleh dibuka dijaga `permission:general-*` per rute.
 */
class EnsureAksesGeneral
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && ($user->isSuperAdmin() || ($user->isStafGeneral() && $user->is_active)), 403);

        return $next($request);
    }
}
