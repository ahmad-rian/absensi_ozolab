<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password && ! $request->routeIs('password.required.*', 'logout')) {
            return to_route('password.required.edit');
        }
        if ($request->routeIs('dashboard') && $request->user()?->hasRole('ORANG_TUA')) {
            return to_route('orangtua.index');
        }

        return $next($request);
    }
}
