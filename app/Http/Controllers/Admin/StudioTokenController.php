<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\StudioToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menerbitkan dan mencabut kredensial Tyas Studio.
 *
 * Hanya SUPER_ADMIN: token boleh dibuat lintas sekolah, dan tidak ada global
 * scope yang menjaga apa pun di jalur ini.
 */
class StudioTokenController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/studio-tokens/index', [
            'tokens' => StudioToken::with(['school:id,name', 'creator:id,name'])
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (StudioToken $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'school' => $t->school?->name,
                    'school_id' => $t->school_id,
                    'created_by' => $t->creator?->name,
                    'created_at' => $t->created_at?->toDateTimeString(),
                    'last_used_at' => $t->last_used_at?->toDateTimeString(),
                    'revoked_at' => $t->revoked_at?->toDateTimeString(),
                ]),
            'schools' => School::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'studioUrl' => config('services.studio.url'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            // Null berarti lintas sekolah — dipakai operator studio yang
            // memotret beberapa sekolah dalam satu hari.
            'school_id' => ['nullable', 'exists:schools,id'],
        ], [], ['name' => 'nama', 'school_id' => 'sekolah']);

        [, $mentah] = StudioToken::terbitkan(
            $data['name'],
            $data['school_id'] ?? null,
            $request->user()->id,
        );

        // SATU-SATUNYA kali nilai ini terlihat. Lewat flash Inertia — sekali
        // pakai, tidak tersimpan di mana pun, dan tidak boleh ikut masuk log.
        Inertia::flash('studioTokenBaru', $mentah);

        return back();
    }

    /**
     * Dicabut, bukan dihapus.
     *
     * Barisnya tetap ada supaya `last_used_at` bisa dibaca saat menelusuri
     * "siapa yang masih memakai token lama".
     */
    public function destroy(StudioToken $studioToken): RedirectResponse
    {
        $studioToken->update(['revoked_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Token dicabut. Pemasangan yang memakainya langsung berhenti bekerja.']);

        return back();
    }
}
