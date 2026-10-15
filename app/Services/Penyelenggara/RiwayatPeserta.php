<?php

namespace App\Services\Penyelenggara;

use App\Enums\AttendanceStatus;
use App\Models\CardFormSubmission;
use App\Models\Kehadiran;
use App\Services\Student\RiwayatAbsensi;
use App\Support\JamDinding;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Riwayat dan statistik kehadiran satu peserta, padanan riwayat absensi di
 * detail siswa ({@see RiwayatAbsensi}).
 *
 * Jam datang hanya dihitung dari baris Hadir/Terlambat: baris Izin/Sakit/Alpa
 * yang dicatat petugas bisa membawa jam catat, dan jam itu bukan jam tiba.
 * `datang_at` sudah jam dinding zona program, jadi dibaca apa adanya lewat
 * {@see JamDinding}.
 */
class RiwayatPeserta
{
    public const PER_HALAMAN = 15;

    public const PARAM = 'riwayat_page';

    /** @var array<int, string> */
    private const DATANG = ['HADIR', 'TERLAMBAT'];

    private const SUMBER = [
        Kehadiran::SUMBER_SCAN => 'Scan',
        Kehadiran::SUMBER_MANUAL => 'Petugas',
        Kehadiran::SUMBER_OTOMATIS => 'Otomatis',
    ];

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function riwayat(CardFormSubmission $peserta, int $halaman): LengthAwarePaginator
    {
        return $this->baris($peserta)
            ->orderByDesc('sesis.tanggal')
            ->orderByDesc('sesis.datang_mulai')
            ->paginate(self::PER_HALAMAN, $this->kolom(), self::PARAM, $halaman)
            ->through(fn (Kehadiran $k) => [
                'id' => $k->id,
                'tanggal' => $k->tanggal,
                'hari' => CarbonImmutable::parse($k->tanggal)->locale('id')->isoFormat('dddd'),
                'sesi' => $k->sesi_name,
                'status' => $k->status,
                'status_label' => AttendanceStatus::from($k->status)->label(),
                'jam_datang' => in_array($k->status, self::DATANG, true) ? JamDinding::format(JamDinding::menit($k->datang_at)) : null,
                'jam_pulang' => JamDinding::format(JamDinding::menit($k->pulang_at)),
                'sumber' => self::SUMBER[$k->sumber] ?? $k->sumber,
                'catatan' => $k->catatan,
            ]);
    }

    /**
     * @return array{total: int, counts: array<string, int>, persen_hadir: float, rata_datang: ?string, paling_awal: ?string, paling_akhir: ?string, rata_terlambat_menit: ?int}
     */
    public function statistik(CardFormSubmission $peserta): array
    {
        $semua = $this->baris($peserta)->get($this->kolom());
        $counts = array_fill_keys(array_map(fn (AttendanceStatus $s) => $s->value, AttendanceStatus::cases()), 0);
        foreach ($semua as $k) {
            $counts[$k->status] = ($counts[$k->status] ?? 0) + 1;
        }

        $datang = $semua->filter(fn (Kehadiran $k) => in_array($k->status, self::DATANG, true) && $k->datang_at !== null);
        $menit = $datang->map(fn (Kehadiran $k) => JamDinding::menit($k->datang_at))->filter(fn (?int $m) => $m !== null);
        $telat = $datang
            ->filter(fn (Kehadiran $k) => $k->status === 'TERLAMBAT' && $k->batas_terlambat)
            ->map(fn (Kehadiran $k) => JamDinding::menit($k->datang_at) - JamDinding::menit($k->batas_terlambat));

        $hadir = $counts['HADIR'] + $counts['TERLAMBAT'];

        return [
            'total' => $semua->count(),
            'counts' => $counts,
            'persen_hadir' => $semua->isEmpty() ? 0.0 : round($hadir / $semua->count() * 100, 1),
            'rata_datang' => JamDinding::format($menit->avg()),
            'paling_awal' => JamDinding::format($menit->min()),
            'paling_akhir' => JamDinding::format($menit->max()),
            'rata_terlambat_menit' => $telat->isEmpty() ? null : (int) round($telat->avg()),
        ];
    }

    /**
     * @return Builder<Kehadiran>
     */
    private function baris(CardFormSubmission $peserta): Builder
    {
        return Kehadiran::query()
            ->join('sesis', 'sesis.id', '=', 'kehadirans.sesi_id')
            ->where('kehadirans.card_form_submission_id', $peserta->id);
    }

    /**
     * @return array<int, string>
     */
    private function kolom(): array
    {
        return [
            'kehadirans.id', 'kehadirans.status', 'kehadirans.datang_at', 'kehadirans.pulang_at',
            'kehadirans.sumber', 'kehadirans.catatan',
            'sesis.tanggal', 'sesis.name as sesi_name', 'sesis.batas_terlambat',
        ];
    }
}
