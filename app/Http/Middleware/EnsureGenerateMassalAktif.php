<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Saklar global untuk generate massal (kartu satu sekolah/kelas, pas foto
 * batch, generate ulang semua kartu haji).
 *
 * Generate massal mendorong ratusan render Chrome + unggahan Drive sekaligus;
 * itulah yang melahirkan folder Drive terbelah dan proses yang mati "Too many
 * open files". Dimatikan sementara lewat `CARDS_GENERATE_MASSAL=false`.
 * Generate ulang per siswa / per peserta tidak lewat sini dan tetap jalan.
 *
 * Global, bukan `feature:` per sekolah: ini keputusan operasional server,
 * bukan pilihan tenant.
 */
class EnsureGenerateMassalAktif
{
    public const PESAN = 'Generate massal sedang dinonaktifkan sementara. Generate ulang per siswa tetap bisa dari halaman siswa.';

    public function handle(Request $request, Closure $next): Response
    {
        if (config('cards.generate_massal')) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => self::PESAN], Response::HTTP_FORBIDDEN);
        }

        Inertia::flash('toast', ['type' => 'warning', 'message' => self::PESAN]);

        return $request->isMethod('GET')
            ? to_route('dashboard')
            : back();
    }
}
