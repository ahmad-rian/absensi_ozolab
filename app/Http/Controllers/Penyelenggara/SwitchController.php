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
        ]);

        $request->session()->put('current_penyelenggara_id', Penyelenggara::findOrFail($validated['penyelenggara_id'])->id);

        return Inertia::location(route('penyelenggara.dashboard'));
    }
}
