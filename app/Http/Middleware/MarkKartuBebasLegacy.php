<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tandai request area Kartu Bebas: tabel kartu hanya menampilkan baris lama
 * (bukan milik penyelenggara). Lihat CardTenancy.
 */
class MarkKartuBebasLegacy
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->instance('kartuBebasLegacy', true);

        return $next($request);
    }
}
