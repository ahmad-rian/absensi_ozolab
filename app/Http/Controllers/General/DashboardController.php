<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use App\Models\CardAttendance;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Models\Kehadiran;
use App\Models\Penyelenggara;
use App\Models\Program;
use App\Models\Sesi;
use App\Models\User;
use App\Services\Penyelenggara\KartuDrive;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ringkasan workspace Absensi General: lintas semua penyelenggara, ditambah
 * kartu haji lama (Kartu Bebas) yang hanya dibaca — tidak ada baris kartu yang
 * disentuh (spec 009, konstitusi III).
 *
 * Semua angka berasal dari query agregat per penyelenggara, bukan per program.
 */
class DashboardController extends Controller
{
    private const ZONA = 'Asia/Jakarta';

    public function __invoke(): Response
    {
        $hariIni = CarbonImmutable::now(self::ZONA)->toDateString();

        $penyelenggaras = Penyelenggara::orderBy('name')->get(['id', 'name', 'is_active', 'participant_quota']);
        $programPer = Program::acrossPenyelenggara()->whereNull('archived_at')
            ->selectRaw('penyelenggara_id, count(*) as total')->groupBy('penyelenggara_id')->pluck('total', 'penyelenggara_id');
        $pesertaPer = CardFormSubmission::acrossPenyelenggara()->whereNotNull('penyelenggara_id')->whereNull('archived_at')
            ->selectRaw('penyelenggara_id, count(*) as total')->groupBy('penyelenggara_id')->pluck('total', 'penyelenggara_id');
        $akunPer = User::whereNotNull('penyelenggara_id')
            ->selectRaw('penyelenggara_id, count(*) as total')->groupBy('penyelenggara_id')->pluck('total', 'penyelenggara_id');

        $kehadiranHariIni = Kehadiran::acrossPenyelenggara()
            ->join('sesis', 'sesis.id', '=', 'kehadirans.sesi_id')
            ->where('sesis.tanggal', $hariIni)
            ->selectRaw("sum(case when kehadirans.status in ('HADIR','TERLAMBAT') then 1 else 0 end) as hadir, count(*) as tercatat")
            ->first();

        $pesertaLama = CardFormSubmission::withoutGlobalScopes()->whereNull('penyelenggara_id');

        return Inertia::render('general/dashboard', [
            'tanggal' => $hariIni,
            'statistik' => [
                'penyelenggara_total' => $penyelenggaras->count(),
                'penyelenggara_aktif' => $penyelenggaras->where('is_active', true)->count(),
                'program_berjalan' => Program::acrossPenyelenggara()->whereNull('archived_at')
                    ->where('starts_on', '<=', $hariIni)->where('ends_on', '>=', $hariIni)->count(),
                'peserta_aktif' => (int) $pesertaPer->sum(),
                'akun' => (int) $akunPer->sum(),
                'sesi_hari_ini' => Sesi::acrossPenyelenggara()->where('tanggal', $hariIni)->count(),
                'hadir_hari_ini' => (int) ($kehadiranHariIni->hadir ?? 0),
                'tercatat_hari_ini' => (int) ($kehadiranHariIni->tercatat ?? 0),
            ],
            'hajiLama' => [
                'layout' => CardForm::withoutGlobalScopes()->whereNull('penyelenggara_id')->count(),
                'peserta' => (clone $pesertaLama)->count(),
                'kartu_jadi' => (clone $pesertaLama)->where('status', 'completed')->count(),
                'hadir_hari_ini' => CardAttendance::whereDate('attendance_date', $hariIni)->where('status', 'hadir')->count(),
            ],
            'driveSiap' => app(KartuDrive::class)->siap(),
            'penyelenggaras' => $penyelenggaras->map(fn (Penyelenggara $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'is_active' => $p->is_active,
                'program' => (int) ($programPer[$p->id] ?? 0),
                'peserta' => (int) ($pesertaPer[$p->id] ?? 0),
                'kuota' => $p->participant_quota,
                'akun' => (int) ($akunPer[$p->id] ?? 0),
            ])->values(),
        ]);
    }
}
