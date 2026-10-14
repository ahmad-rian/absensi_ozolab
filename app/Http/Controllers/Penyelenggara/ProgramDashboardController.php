<?php

namespace App\Http\Controllers\Penyelenggara;

use App\Http\Controllers\Controller;
use App\Models\Grup;
use App\Models\Program;
use App\Models\Sesi;
use App\Services\Penyelenggara\PesertaService;
use App\Services\Penyelenggara\RingkasanKehadiran;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard program: ringkasan per sesi hari ini, belum hadir, peringkat & tren (spec 005).
 */
class ProgramDashboardController extends Controller
{
    public function show(Request $request, Program $program, RingkasanKehadiran $ringkasan, PesertaService $peserta): Response
    {
        $hariIni = CarbonImmutable::now($program->timezone);
        $sesis = Sesi::where('program_id', $program->id)->where('tanggal', $hariIni->toDateString())->orderBy('datang_mulai')->get();
        $grupId = $request->input('grup') ?: null;
        $bulan = in_array((int) $request->input('bulan'), [1, 2, 3], true) ? (int) $request->input('bulan') : 1;
        $berjalan7Hari = $program->starts_on->lte($hariIni->subDays(7));
        $user = $request->user();
        $grupTerlihat = $peserta->grupTerlihat($user, $program);

        return Inertia::render('penyelenggara/program/dashboard', [
            'program' => ['id' => $program->id, 'name' => $program->name, 'tanggal' => $hariIni->toDateString()],
            'adaSesiHariIni' => $sesis->isNotEmpty(),
            'grups' => Grup::where('program_id', $program->id)
                ->when($grupTerlihat !== null, fn ($q) => $q->whereIn('id', $grupTerlihat ?: ['-']))
                ->orderBy('urutan')->orderBy('name')->get(['id', 'name']),
            'filters' => ['grup' => $grupId ?? '', 'bulan' => $bulan],
            'peringkatTersedia' => $berjalan7Hari,
            'ringkasan' => Inertia::defer(fn () => $sesis->map(fn (Sesi $s) => $ringkasan->ringkasSesi($program, $s, $user, $grupId))->values()),
            'peringkat' => Inertia::defer(fn () => $berjalan7Hari ? $ringkasan->peringkat($program, $user, $bulan) : [], 'peringkat'),
            'tren' => Inertia::defer(fn () => $berjalan7Hari ? $ringkasan->tren($program, $user, $bulan) : [], 'peringkat'),
        ]);
    }
}
