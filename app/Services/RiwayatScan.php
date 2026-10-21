<?php

namespace App\Services;

use App\Models\CardAttendance;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
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

    /**
     * Haji lama (Kartu Bebas): satu baris per kejadian masuk/pulang.
     *
     * @return list<array{nama: string, jenis: string, jam: string, status: string}>
     */
    public function hajiLama(CardForm $form): array
    {
        $baris = CardAttendance::query()
            ->with('participant')
            ->whereHas('participant', fn ($q) => $q->where('card_form_id', $form->id))
            ->where('attendance_date', SchoolTime::todayString())
            ->latest('updated_at')
            ->limit(self::BATAS)
            ->get();

        return $this->urutkan($baris->flatMap(fn (CardAttendance $a) => array_filter([
            $a->check_in ? $this->kejadian($a->participant, 'Masuk', $a->check_in, $a->status) : null,
            $a->check_out ? $this->kejadian($a->participant, 'Pulang', $a->check_out, $a->status) : null,
        ])));
    }

    /**
     * Program penyelenggara: kejadian datang/pulang di semua sesi hari ini.
     *
     * @return list<array{nama: string, jenis: string, jam: string, status: string}>
     */
    public function program(Program $program): array
    {
        $sesiHariIni = Sesi::acrossPenyelenggara()
            ->where('program_id', $program->id)
            ->where('tanggal', CarbonImmutable::now($program->timezone)->toDateString())
            ->pluck('name', 'id');

        $baris = Kehadiran::acrossPenyelenggara()
            ->with(['peserta' => fn ($q) => $q->withoutGlobalScopes()])
            ->whereIn('sesi_id', $sesiHariIni->keys())
            ->latest('updated_at')
            ->limit(self::BATAS)
            ->get();

        return $this->urutkan($baris->flatMap(fn (Kehadiran $k) => array_filter([
            $k->datang_at ? $this->kejadian($k->peserta, 'Datang', $k->datang_at->format('H:i:s'), $k->status, $sesiHariIni[$k->sesi_id] ?? null) : null,
            $k->pulang_at ? $this->kejadian($k->peserta, 'Pulang', $k->pulang_at->format('H:i:s'), $k->status, $sesiHariIni[$k->sesi_id] ?? null) : null,
        ])));
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
