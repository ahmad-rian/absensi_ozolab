<?php

namespace App\Http\Controllers\Penyelenggara;

use App\Http\Controllers\Controller;
use App\Models\Kehadiran;
use App\Models\Program;
use Carbon\CarbonImmutable;
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
            // Ringkasan cepat; angka rinci per sesi ada di dashboard program.
            'programs' => Program::whereNull('archived_at')->with('kategori:id,name')->orderByDesc('starts_on')->limit(20)->get()
                ->map(function (Program $p) {
                    $hariIni = CarbonImmutable::now($p->timezone)->toDateString();
                    $hadir = Kehadiran::where('program_id', $p->id)
                        ->whereIn('status', ['HADIR', 'TERLAMBAT'])
                        ->whereHas('sesi', fn ($q) => $q->where('tanggal', $hariIni))
                        ->count();

                    return [
                        'id' => $p->id,
                        'name' => $p->name,
                        'kategori' => $p->kategori?->name,
                        'peserta' => $p->peserta()->whereNull('archived_at')->count(),
                        'hadir_hari_ini' => $hadir,
                    ];
                }),
        ]);
    }
}
