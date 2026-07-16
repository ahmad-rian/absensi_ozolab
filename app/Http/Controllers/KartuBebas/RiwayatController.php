<?php

namespace App\Http\Controllers\KartuBebas;

use App\Http\Controllers\Controller;
use App\Models\CardFormSubmission;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class RiwayatController extends Controller
{
    public function index(): Response
    {
        $submissions = CardFormSubmission::with('cardForm')
            ->where('status', 'completed')
            ->latest()
            ->limit(100)
            ->get();

        return Inertia::render('kartu-bebas/riwayat/index', [
            'submissions' => $submissions->map(fn (CardFormSubmission $s) => [
                'id' => $s->id,
                'layout_name' => $s->cardForm?->name ?? '-',
                'status' => $s->status,
                'card_url' => $s->drive_url ?? ($s->file_path ? Storage::disk('public')->url($s->file_path) : null),
                'created_at' => $s->created_at?->toIso8601String(),
            ]),
        ]);
    }
}
