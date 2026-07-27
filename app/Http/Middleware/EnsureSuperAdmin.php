<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Untuk modul yang menyentuh data lintas sekolah.
 *
 * Model `School`, `Role`, dan `SchoolNotificationChannel` sengaja tidak
 * ber-tenant, jadi global scope `school` tidak melindungi apa pun di sana —
 * permission saja tidak cukup, pemanggilnya harus benar-benar Super Admin.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        return $next($request);
    }
}
