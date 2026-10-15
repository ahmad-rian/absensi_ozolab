<?php

namespace App\Http\Controllers\Penyelenggara;

use App\Http\Controllers\Controller;
use App\Models\Penyelenggara;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Super admin memilih penyelenggara yang sedang dibuka (per browser, di session).
 */
class SwitchController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $validated = $request->validate([
            'penyelenggara_id' => ['required', 'exists:penyelenggaras,id'],
            'kembali' => ['nullable', 'string', 'max:300'],
        ]);

        $request->session()->put('current_penyelenggara_id', Penyelenggara::findOrFail($validated['penyelenggara_id'])->id);

        // Tombol "Buka" di workspace general langsung menuju halaman tertentu.
        // Hanya jalur relatif di area penyelenggara yang diterima, supaya
        // parameter ini tidak bisa dipakai sebagai open redirect.
        $kembali = $validated['kembali'] ?? '';
        $aman = preg_match('#^/penyelenggara(/[A-Za-z0-9/_-]*)?$#', $kembali) === 1;

        return Inertia::location($aman ? $kembali : route('penyelenggara.dashboard'));
    }
}
