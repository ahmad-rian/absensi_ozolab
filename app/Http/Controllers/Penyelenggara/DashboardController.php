<?php

namespace App\Http\Controllers\Penyelenggara;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Beranda area penyelenggara. Isinya bertambah di spec 002–005.
 */
class DashboardController extends Controller
{
    public function index(): Response
    {
        $penyelenggara = app()->bound('currentPenyelenggara') ? app('currentPenyelenggara') : null;

        if (! $penyelenggara) {
            return Inertia::render('penyelenggara/pilih');
        }

        return Inertia::render('penyelenggara/dashboard', [
            'penyelenggara' => [
                'id' => $penyelenggara->id,
                'name' => $penyelenggara->name,
                'participant_quota' => $penyelenggara->participant_quota,
            ],
        ]);
    }
}
