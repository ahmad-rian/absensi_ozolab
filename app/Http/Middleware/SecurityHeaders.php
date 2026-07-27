<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan dasar.
 *
 * `nosniff` yang paling berdampak di sini: berkas unggahan disajikan dari
 * origin yang sama dengan aplikasi, jadi berkas ber-signature gambar palsu
 * tidak boleh sampai ditafsirkan browser sebagai HTML.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        return $response;
    }
}
