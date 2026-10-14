<?php

namespace App\Services\Penyelenggara;

use App\Enums\AttendanceStatus;
use App\Models\CardFormSubmission;
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

    public function __construct(
        private SesiResolver $resolver,
        private CardAttendanceService $kartu,
    ) {}

    /**
     * @return array{kehadiran: Kehadiran, peserta: CardFormSubmission, sesi: Sesi, mode: string, duplikat: bool}
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

        if ($mode === null) {
            $this->tolak("Di luar jam scan {$sesi->name}. Jendela pulang mulai ".substr((string) $sesi->pulang_mulai, 0, 5).'.');
        }

        try {
            // 3 percobaan: InnoDB melaporkan dua scan bersamaan atas baris yang
            // belum ada sebagai deadlock (gap lock), bukan unique violation.
            return DB::transaction(fn () => $this->catat($program, $sesi, $peserta, $mode, $sekarang), 3);
        } catch (UniqueConstraintViolationException) {
            // Dua scan bersamaan untuk peserta yang sama: yang kalah membaca baris pemenang.
            return DB::transaction(fn () => $this->catat($program, $sesi, $peserta, $mode, $sekarang));
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
     * @return array{kehadiran: Kehadiran, peserta: CardFormSubmission, sesi: Sesi, mode: string, duplikat: bool}
     */
    private function catat(Program $program, Sesi $sesi, CardFormSubmission $peserta, string $mode, CarbonImmutable $sekarang): array
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

        if ($mode === self::MODE_DATANG) {
            if ($kehadiran?->datang_at) {
                return ['kehadiran' => $kehadiran, 'peserta' => $peserta, 'sesi' => $sesi, 'mode' => $mode, 'duplikat' => true];
            }

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

            return ['kehadiran' => $kehadiran, 'peserta' => $peserta, 'sesi' => $sesi, 'mode' => $mode, 'duplikat' => false];
        }

        if (! $kehadiran?->datang_at) {
            $this->tolak("Belum tercatat datang di {$sesi->name}, jadi pulang tidak bisa dicatat. Hubungi admin.");
        }
        if ($kehadiran->pulang_at) {
            return ['kehadiran' => $kehadiran, 'peserta' => $peserta, 'sesi' => $sesi, 'mode' => $mode, 'duplikat' => true];
        }

        $kehadiran->forceFill(['pulang_at' => $wall])->save();

        return ['kehadiran' => $kehadiran, 'peserta' => $peserta, 'sesi' => $sesi, 'mode' => $mode, 'duplikat' => false];
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
