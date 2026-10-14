<?php

namespace App\Services\Penyelenggara;

use App\Models\CardFormSubmission;
use App\Models\Grup;
use App\Models\Kehadiran;
use App\Models\Program;
use App\Models\Sesi;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Satu-satunya sumber angka kehadiran program (spec 005): dashboard dan semua
 * ekspor memakai kelas ini, jadi angkanya sama secara konstruksi (FR-005).
 */
class RingkasanKehadiran
{
    public const STATUS = ['HADIR', 'TERLAMBAT', 'IZIN', 'SAKIT', 'ALPA', 'BELUM'];

    public function __construct(private PesertaService $peserta) {}

    /**
     * Peserta target sebuah sesi dalam cakupan user (dan filter grup bila ada).
     *
     * @return Collection<int, CardFormSubmission>
     */
    public function target(Program $program, Sesi $sesi, User $user, ?string $grupId = null): Collection
    {
        $query = CardFormSubmission::where('program_id', $program->id)->whereNull('archived_at')->with('grups:id,name');

        if ($sesi->grup_id) {
            $query->whereHas('grups', fn ($q) => $q->whereIn('grups.id', Grup::denganTurunan($program->id, [$sesi->grup_id])));
        }
        if ($grupId) {
            $query->whereHas('grups', fn ($q) => $q->whereIn('grups.id', Grup::denganTurunan($program->id, [$grupId])));
        }

        return $this->peserta->batasi($query, $user, $program)->get();
    }

    /**
     * @return array{sesi: array<string, mixed>, target: int, counts: array<string, int>, belum: array<int, array{id: string, nama: string, grups: string}>}
     */
    public function ringkasSesi(Program $program, Sesi $sesi, User $user, ?string $grupId = null): array
    {
        $target = $this->target($program, $sesi, $user, $grupId);
        $status = Kehadiran::where('sesi_id', $sesi->id)->whereIn('card_form_submission_id', $target->pluck('id'))->pluck('status', 'card_form_submission_id');

        $counts = array_fill_keys(self::STATUS, 0);
        $belum = [];
        foreach ($target as $p) {
            $s = $status[$p->id] ?? 'BELUM';
            $counts[$s]++;
            if ($s === 'BELUM') {
                $belum[] = ['id' => $p->id, 'nama' => $p->data['nama'] ?? '—', 'grups' => $p->grups->pluck('name')->implode(', ')];
            }
        }

        return [
            'sesi' => ['id' => $sesi->id, 'name' => $sesi->name, 'datang' => substr($sesi->datang_mulai, 0, 5).'–'.substr($sesi->datang_selesai, 0, 5)],
            'target' => $target->count(),
            'counts' => $counts,
            'belum' => collect($belum)->sortBy('nama')->values()->all(),
        ];
    }

    /**
     * Matriks sesi × peserta untuk rentang tanggal.
     *
     * @return array{sesis: Collection<int, Sesi>, peserta: Collection<int, CardFormSubmission>, status: array<string, array<string, Kehadiran>>}
     */
    public function matriks(Program $program, string $dari, string $sampai, User $user, ?string $grupId = null): array
    {
        $sesis = Sesi::where('program_id', $program->id)
            ->where('tanggal', '>=', $dari)
            ->where('tanggal', '<=', $sampai)
            ->orderBy('tanggal')->orderBy('datang_mulai')
            ->get();

        $query = CardFormSubmission::where('program_id', $program->id)->whereNull('archived_at')->with('grups:id,name');
        if ($grupId) {
            $query->whereHas('grups', fn ($q) => $q->whereIn('grups.id', Grup::denganTurunan($program->id, [$grupId])));
        }
        $peserta = $this->peserta->batasi($query, $user, $program)->get()->sortBy(fn ($p) => $p->data['nama'] ?? '')->values();

        $status = [];
        Kehadiran::whereIn('sesi_id', $sesis->pluck('id'))
            ->whereIn('card_form_submission_id', $peserta->pluck('id'))
            ->get()
            ->each(function (Kehadiran $k) use (&$status) {
                $status[$k->sesi_id][$k->card_form_submission_id] = $k;
            });

        return ['sesis' => $sesis, 'peserta' => $peserta, 'status' => $status];
    }

    /**
     * Apakah peserta termasuk target sesi (sesi khusus grup).
     *
     * @param  array<string, array<int, string>>  $turunanCache
     */
    public function termasukTarget(Program $program, Sesi $sesi, CardFormSubmission $peserta, array &$turunanCache): bool
    {
        if (! $sesi->grup_id) {
            return true;
        }
        $turunanCache[$sesi->grup_id] ??= Grup::denganTurunan($program->id, [$sesi->grup_id]);

        return $peserta->grups->pluck('id')->intersect($turunanCache[$sesi->grup_id])->isNotEmpty();
    }

    /**
     * Rata-rata menit datang setelah jendela dibuka — kecil = cepat (P3).
     *
     * @return array<int, array{nama: string, rata_menit: float, hari: int}>
     */
    public function peringkat(Program $program, User $user, int $bulan = 1, int $batas = 10): array
    {
        $sejak = CarbonImmutable::now($program->timezone)->subMonths($bulan)->toDateString();
        $peserta = $this->peserta->batasi(CardFormSubmission::where('program_id', $program->id)->whereNull('archived_at'), $user, $program)->pluck('data', 'id');

        $baris = Kehadiran::query()
            ->join('sesis', 'sesis.id', '=', 'kehadirans.sesi_id')
            ->where('kehadirans.program_id', $program->id)
            ->whereNotNull('kehadirans.datang_at')
            ->where('sesis.tanggal', '>=', $sejak)
            ->whereIn('kehadirans.card_form_submission_id', $peserta->keys())
            ->get(['kehadirans.card_form_submission_id', 'kehadirans.datang_at', 'sesis.datang_mulai']);

        return $baris->groupBy('card_form_submission_id')
            ->filter(fn ($rows) => $rows->count() >= 3)
            ->map(function ($rows, $id) use ($peserta) {
                $menit = $rows->map(function ($r) {
                    [$h, $m] = array_map('intval', explode(':', $r->datang_mulai));

                    return ($r->datang_at->hour * 60 + $r->datang_at->minute) - ($h * 60 + $m);
                });

                return ['nama' => $peserta[$id]['nama'] ?? '—', 'rata_menit' => round($menit->avg(), 1), 'hari' => $rows->count()];
            })
            ->sortBy('rata_menit')
            ->take($batas)
            ->values()
            ->all();
    }

    /**
     * Persentase hadir (HADIR+TERLAMBAT) per minggu (P3).
     *
     * @return array<int, array{minggu: string, persen: float, total: int}>
     */
    public function tren(Program $program, User $user, int $bulan = 1): array
    {
        $sejak = CarbonImmutable::now($program->timezone)->subMonths($bulan)->toDateString();
        $peserta = $this->peserta->batasi(CardFormSubmission::where('program_id', $program->id)->whereNull('archived_at'), $user, $program)->pluck('id');

        return Kehadiran::query()
            ->join('sesis', 'sesis.id', '=', 'kehadirans.sesi_id')
            ->where('kehadirans.program_id', $program->id)
            ->where('sesis.tanggal', '>=', $sejak)
            ->whereIn('kehadirans.card_form_submission_id', $peserta)
            ->get(['kehadirans.status', 'sesis.tanggal'])
            ->groupBy(fn ($r) => CarbonImmutable::parse($r->tanggal)->startOfWeek()->toDateString())
            ->sortKeys()
            ->map(fn ($rows, $minggu) => [
                'minggu' => $minggu,
                'persen' => round($rows->whereIn('status', ['HADIR', 'TERLAMBAT'])->count() / max(1, $rows->count()) * 100, 1),
                'total' => $rows->count(),
            ])
            ->values()
            ->all();
    }
}
