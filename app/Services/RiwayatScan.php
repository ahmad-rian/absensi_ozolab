<?php

namespace App\Services;

use App\Models\CardAttendance;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Models\CardScan;
use App\Models\Kehadiran;
use App\Models\Program;
use App\Models\Sesi;
use App\Support\SchoolTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Riwayat scan hari ini untuk halaman scan peserta, dibaca dari database —
 * bukan disimpan di memori tab. Semua perangkat yang membuka link scan yang
 * sama melihat daftar yang sama, dengan jam server.
 *
 * Dipanggil dari rute publik (tanpa login), jadi semua query melewati scope
 * tenant secara eksplisit dan dibatasi ke layout / program pemilik link.
 */
class RiwayatScan
{
    public const BATAS = 20;

    public function __construct(private CardAttendanceService $kartu) {}

    /** Label jenis scan di riwayat. */
    private const LABEL = [
        CardScan::JENIS_MASUK => 'Masuk',
        CardScan::JENIS_DATANG => 'Datang',
        CardScan::JENIS_PULANG => 'Pulang',
        CardScan::JENIS_SCAN => 'Scan',
    ];

    /**
     * Haji lama (Kartu Bebas): setiap scan hari ini, terbaru dulu.
     *
     * Peserta yang tercatat sebelum `card_scans` ada (hari deploy) masih
     * ditampilkan dari jam masuk/pulang di rekap hariannya.
     *
     * @return list<array{nama: string, jenis: string, jam: string, status: string}>
     */
    public function hajiLama(CardForm $form): array
    {
        $awalHari = SchoolTime::todayString().' 00:00:00';

        $scan = CardScan::query()
            ->with('peserta')
            ->where('card_form_id', $form->id)
            ->where('scanned_at', '>=', $awalHari)
            ->latest('scanned_at')
            ->limit(self::BATAS)
            ->get();

        $kejadian = $scan->map(fn (CardScan $s) => $this->kejadian($s->peserta, self::LABEL[$s->jenis] ?? 'Scan', $s->scanned_at->format('H:i:s'), 'hadir'));

        $lama = CardAttendance::query()
            ->with('participant')
            ->whereHas('participant', fn ($q) => $q->where('card_form_id', $form->id))
            ->where('attendance_date', SchoolTime::todayString())
            ->whereNotIn('card_form_submission_id', CardScan::query()
                ->select('card_form_submission_id')
                ->where('card_form_id', $form->id)
                ->where('scanned_at', '>=', $awalHari))
            ->latest('updated_at')
            ->limit(self::BATAS)
            ->get();

        return $this->urutkan($kejadian->concat($lama->flatMap(fn (CardAttendance $a) => array_filter([
            $a->check_in ? $this->kejadian($a->participant, 'Masuk', $a->check_in, $a->status) : null,
            $a->check_out ? $this->kejadian($a->participant, 'Pulang', $a->check_out, $a->status) : null,
        ]))));
    }

    /**
     * Program penyelenggara: setiap scan di semua sesi hari ini, terbaru dulu.
     *
     * @return list<array{nama: string, jenis: string, jam: string, status: string}>
     */
    public function program(Program $program): array
    {
        $sesiHariIni = Sesi::acrossPenyelenggara()
            ->where('program_id', $program->id)
            ->where('tanggal', CarbonImmutable::now($program->timezone)->toDateString())
            ->pluck('name', 'id');

        $scan = CardScan::query()
            ->with(['peserta' => fn ($q) => $q->withoutGlobalScopes()])
            ->whereIn('sesi_id', $sesiHariIni->keys())
            ->latest('scanned_at')
            ->limit(self::BATAS)
            ->get();

        $kunci = fn ($baris): string => $baris->sesi_id.'|'.$baris->card_form_submission_id;
        // Status hanya untuk peserta yang muncul di 20 scan terakhir.
        $status = Kehadiran::acrossPenyelenggara()
            ->whereIn('sesi_id', $sesiHariIni->keys())
            ->whereIn('card_form_submission_id', $scan->pluck('card_form_submission_id')->unique())
            ->get(['sesi_id', 'card_form_submission_id', 'status'])
            ->mapWithKeys(fn (Kehadiran $k) => [$kunci($k) => $k->status]);

        $kejadian = $scan->map(fn (CardScan $s) => $this->kejadian(
            $s->peserta,
            self::LABEL[$s->jenis] ?? 'Scan',
            $s->scanned_at->format('H:i:s'),
            $status[$kunci($s)] ?? 'HADIR',
            $sesiHariIni[$s->sesi_id] ?? null,
        ));

        // Kehadiran yang tercatat sebelum `card_scans` ada (hari deploy).
        $lama = Kehadiran::acrossPenyelenggara()
            ->with(['peserta' => fn ($q) => $q->withoutGlobalScopes()])
            ->whereIn('sesi_id', $sesiHariIni->keys())
            ->whereNotNull('datang_at')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('card_scans')
                ->whereColumn('card_scans.sesi_id', 'kehadirans.sesi_id')
                ->whereColumn('card_scans.card_form_submission_id', 'kehadirans.card_form_submission_id'))
            ->latest('updated_at')
            ->limit(self::BATAS)
            ->get();

        return $this->urutkan($kejadian->concat($lama->flatMap(fn (Kehadiran $k) => array_filter([
            $k->datang_at ? $this->kejadian($k->peserta, 'Datang', $k->datang_at->format('H:i:s'), $k->status, $sesiHariIni[$k->sesi_id] ?? null) : null,
            $k->pulang_at ? $this->kejadian($k->peserta, 'Pulang', $k->pulang_at->format('H:i:s'), $k->status, $sesiHariIni[$k->sesi_id] ?? null) : null,
        ]))));
    }

    /**
     * @return array{nama: string, jenis: string, jam: string, status: string, sesi: ?string}
     */
    private function kejadian(?CardFormSubmission $peserta, string $jenis, string $jam, string $status, ?string $sesi = null): array
    {
        return [
            'nama' => $peserta ? $this->kartu->participantName($peserta) : 'Peserta terhapus',
            'jenis' => $jenis,
            'jam' => substr($jam, 0, 8),
            'status' => $status,
            'sesi' => $sesi,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $kejadian
     * @return list<array<string, mixed>>
     */
    private function urutkan(Collection $kejadian): array
    {
        return $kejadian->sortByDesc('jam')->take(self::BATAS)->values()->all();
    }
}
