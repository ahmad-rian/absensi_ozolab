<?php

namespace App\Services\Penyelenggara;

use App\Enums\AttendanceStatus;
use App\Models\CardFormSubmission;
use App\Models\CardScan;
use App\Models\Kehadiran;
use App\Models\Program;
use App\Models\Sesi;
use App\Models\User;
use App\Services\CardAttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pencatatan kehadiran program penyelenggara (spec 004), pola AttendanceRecorder:
 * server yang memutuskan sesi, datang/pulang, dan status.
 *
 * Sengaja terpisah dari CardAttendanceService (Kartu Bebas/haji lama); hanya
 * `qrToken()` miliknya yang dipakai untuk memverifikasi kartu, tanpa mengubahnya.
 */
class KehadiranRecorder
{
    public const MODE_DATANG = 'datang';

    public const MODE_PULANG = 'pulang';

    /** Scan di antara jendela datang dan pulang; hanya dicatat di riwayat. */
    public const MODE_SCAN = 'scan';

    public function __construct(
        private SesiResolver $resolver,
        private CardAttendanceService $kartu,
    ) {}

    /**
     * @return array{kehadiran: Kehadiran, peserta: CardFormSubmission, sesi: Sesi, mode: string, duplikat: bool, jenis: ?string, urutan: int}
     *
     * @throws ValidationException pesan untuk layar petugas
     */
    public function scan(Program $program, string $token, ?CarbonImmutable $waktu = null): array
    {
        $peserta = $this->pesertaDariToken($program, $token);
        $hasil = $this->resolver->untuk($peserta, $waktu);
        $sekarang = $hasil['sekarang'];

        if ($hasil['libur']) {
            $this->tolak('Hari ini libur'.($hasil['libur']->keterangan ? " ({$hasil['libur']->keterangan})" : '').' — tidak ada sesi.');
        }

        $sesi = $hasil['sesi'];
        if (! $sesi) {
            $this->tolak($hasil['berikutnya']
                ? 'Tidak ada sesi sekarang · berikutnya '.$hasil['berikutnya']->name.' '.substr($hasil['berikutnya']->datang_mulai, 0, 5).'.'
                : 'Tidak ada sesi lagi hari ini.');
        }

        $jam = $sekarang->format('H:i:s');
        $mode = $jam <= $sesi->datang_selesai ? self::MODE_DATANG
            : ($sesi->pulang_mulai && $jam >= $sesi->pulang_mulai ? self::MODE_PULANG : null);

        // Di antara jendela datang dan pulang: peserta yang sudah datang tetap
        // boleh scan (masuk riwayat); yang belum datang ditolak dengan pesan ini.
        $pesanLuarJendela = $sesi->pulang_mulai
            ? "Di luar jam scan {$sesi->name}. Jendela pulang mulai ".substr($sesi->pulang_mulai, 0, 5).'.'
            : "Jendela datang {$sesi->name} sudah tutup.";
        $mode ??= self::MODE_SCAN;

        try {
            // 3 percobaan: InnoDB melaporkan dua scan bersamaan atas baris yang
            // belum ada sebagai deadlock (gap lock), bukan unique violation.
            return DB::transaction(fn () => $this->catat($program, $sesi, $peserta, $mode, $sekarang, $pesanLuarJendela), 3);
        } catch (UniqueConstraintViolationException) {
            // Dua scan bersamaan untuk peserta yang sama: yang kalah membaca baris pemenang.
            return DB::transaction(fn () => $this->catat($program, $sesi, $peserta, $mode, $sekarang, $pesanLuarJendela));
        }
    }

    /**
     * Status manual oleh admin/pendamping (FR-005). Log aktivitas mencatat nilai lama & baru.
     */
    public function catatManual(Sesi $sesi, CardFormSubmission $peserta, AttendanceStatus $status, ?string $catatan, User $oleh): Kehadiran
    {
        return DB::transaction(function () use ($sesi, $peserta, $status, $catatan, $oleh) {
            $kehadiran = Kehadiran::acrossPenyelenggara()
                ->where('sesi_id', $sesi->id)
                ->where('card_form_submission_id', $peserta->id)
                ->lockForUpdate()
                ->first() ?? new Kehadiran([
                    'penyelenggara_id' => $sesi->penyelenggara_id,
                    'program_id' => $sesi->program_id,
                    'sesi_id' => $sesi->id,
                    'card_form_submission_id' => $peserta->id,
                ]);

            $kehadiran->fill([
                'status' => $status->value,
                'catatan' => $catatan,
                'sumber' => Kehadiran::SUMBER_MANUAL,
                'dicatat_oleh' => $oleh->id,
            ])->save();

            return $kehadiran;
        });
    }

    /**
     * Datang = scan pertama (tidak ditimpa), Pulang = scan terakhir di jendela
     * pulang (selalu diperbarui); scan lain hanya masuk riwayat `card_scans`.
     *
     * @return array{kehadiran: Kehadiran, peserta: CardFormSubmission, sesi: Sesi, mode: string, duplikat: bool, jenis: ?string, urutan: int}
     */
    private function catat(Program $program, Sesi $sesi, CardFormSubmission $peserta, string $mode, CarbonImmutable $sekarang, string $pesanLuarJendela): array
    {
        $kehadiran = Kehadiran::acrossPenyelenggara()
            ->where('sesi_id', $sesi->id)
            ->where('card_form_submission_id', $peserta->id)
            ->lockForUpdate()
            ->first();

        if ($kehadiran && $kehadiran->sumber === Kehadiran::SUMBER_MANUAL && in_array($kehadiran->status, ['IZIN', 'SAKIT', 'ALPA'], true)) {
            $this->tolak('Sudah dicatat '.AttendanceStatus::from($kehadiran->status)->label().' oleh petugas untuk '.$sesi->name.'.');
        }
        if ($kehadiran && $kehadiran->sumber === Kehadiran::SUMBER_OTOMATIS && $kehadiran->status === 'ALPA') {
            $this->tolak("Sudah ditandai Alpa untuk {$sesi->name}. Hubungi admin bila peserta sebenarnya hadir.");
        }

        $wall = $sekarang->format('Y-m-d H:i:s');
        $hadirManual = $kehadiran && $kehadiran->sumber === Kehadiran::SUMBER_MANUAL && in_array($kehadiran->status, ['HADIR', 'TERLAMBAT'], true);
        $sudahDatang = (bool) $kehadiran?->datang_at || $hadirManual;

        if ($mode === self::MODE_SCAN && ! $sudahDatang) {
            $this->tolak($pesanLuarJendela);
        }
        if ($mode === self::MODE_PULANG && ! $sudahDatang) {
            $this->tolak("Belum tercatat datang di {$sesi->name}, jadi pulang tidak bisa dicatat. Hubungi admin.");
        }

        // Scanner dobel / kartu ditempel dua kali: diabaikan tanpa menulis apa pun.
        if ($kehadiran && CardScan::baruSaja($peserta->id, $wall)) {
            return $this->hasil($kehadiran, $peserta, $sesi, $mode, true, null);
        }

        if ($mode === self::MODE_DATANG && $kehadiran?->datang_at) {
            $jenis = CardScan::JENIS_SCAN;
        } elseif ($mode === self::MODE_DATANG && $hadirManual) {
            // Sudah dicatat Hadir/Terlambat oleh petugas: status manual dipertahankan,
            // hanya jam datangnya yang dilengkapi.
            $kehadiran->forceFill(['datang_at' => $wall])->save();
            $jenis = CardScan::JENIS_DATANG;
        } elseif ($mode === self::MODE_DATANG) {
            $terlambat = $sesi->batas_terlambat && $sekarang->format('H:i:s') > $sesi->batas_terlambat;
            $kehadiran ??= new Kehadiran([
                'penyelenggara_id' => $program->penyelenggara_id,
                'program_id' => $program->id,
                'sesi_id' => $sesi->id,
                'card_form_submission_id' => $peserta->id,
            ]);
            $kehadiran->fill([
                'status' => ($terlambat ? AttendanceStatus::Terlambat : AttendanceStatus::Hadir)->value,
                'datang_at' => $wall,
                'sumber' => Kehadiran::SUMBER_SCAN,
            ])->save();
            $jenis = CardScan::JENIS_DATANG;
        } elseif ($mode === self::MODE_PULANG) {
            $kehadiran->forceFill(['pulang_at' => $wall])->save();
            $jenis = CardScan::JENIS_PULANG;
        } else {
            $jenis = CardScan::JENIS_SCAN;
        }

        CardScan::create([
            'card_form_submission_id' => $peserta->id,
            'program_id' => $program->id,
            'sesi_id' => $sesi->id,
            'jenis' => $jenis,
            'scanned_at' => $wall,
        ]);

        return $this->hasil($kehadiran, $peserta, $sesi, $mode, false, $jenis);
    }

    /**
     * @return array{kehadiran: Kehadiran, peserta: CardFormSubmission, sesi: Sesi, mode: string, duplikat: bool, jenis: ?string, urutan: int}
     */
    private function hasil(Kehadiran $kehadiran, CardFormSubmission $peserta, Sesi $sesi, string $mode, bool $duplikat, ?string $jenis): array
    {
        $urutan = CardScan::query()
            ->where('card_form_submission_id', $peserta->id)
            ->where('sesi_id', $sesi->id)
            ->count();

        return compact('kehadiran', 'peserta', 'sesi', 'mode', 'duplikat', 'jenis', 'urutan');
    }

    private function pesertaDariToken(Program $program, string $token): CardFormSubmission
    {
        $token = strtolower(trim($token));

        if (! preg_match('/\Akb\.([0-9a-hjkmnp-tv-z]{26})\.[a-f0-9]{32}\z/', $token, $m)) {
            $this->tolak('QR ini bukan kartu absensi peserta. Gunakan kartu yang dicetak dari program ini.');
        }

        $peserta = CardFormSubmission::acrossPenyelenggara()
            ->whereKey($m[1])
            ->where('program_id', $program->id)
            ->first();

        if (! $peserta || ! hash_equals($this->kartu->qrToken($peserta), $token)) {
            $this->tolak('Kartu ini bukan milik peserta program '.$program->name.'.');
        }
        if ($peserta->archived_at) {
            $this->tolak('Peserta ini sudah diarsip. Hubungi admin untuk memulihkannya.');
        }

        return $peserta;
    }

    /**
     * @throws ValidationException
     */
    private function tolak(string $pesan): never
    {
        throw ValidationException::withMessages(['token' => $pesan]);
    }
}
