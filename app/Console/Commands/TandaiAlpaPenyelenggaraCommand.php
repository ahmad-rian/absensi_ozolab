<?php

namespace App\Console\Commands;

use App\Enums\AttendanceStatus;
use App\Models\CardFormSubmission;
use App\Models\Grup;
use App\Models\Kehadiran;
use App\Models\Libur;
use App\Models\Program;
use App\Models\Sesi;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Tandai Alpa peserta yang tidak tercatat apa pun setelah jendela datang sesi
 * tutup (spec 004 FR-006). Berjalan tiap jam, aman diulang.
 *
 * Hanya sesi 2 hari terakhir yang diperiksa; peserta yang terdaftar setelah
 * tanggal sesi tidak dialpa-kan mundur.
 */
class TandaiAlpaPenyelenggaraCommand extends Command
{
    protected $signature = 'penyelenggara:tandai-alpa {--program= : Hanya program dengan id ini} {--dry-run : Hitung tanpa menyimpan}';

    protected $description = 'Tandai Alpa peserta program penyelenggara yang tidak scan setelah jendela datang tutup';

    public function handle(): int
    {
        $total = 0;
        $simulasi = (bool) $this->option('dry-run');

        $programs = Program::acrossPenyelenggara()
            ->whereNull('archived_at')
            ->when($this->option('program'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        foreach ($programs as $program) {
            $sekarang = CarbonImmutable::now($program->timezone);
            $hariIni = $sekarang->toDateString();
            $libur = Libur::acrossPenyelenggara()->where('program_id', $program->id)->pluck('tanggal')->flip();

            $sesis = Sesi::acrossPenyelenggara()
                ->where('program_id', $program->id)
                ->whereIn('tanggal', [$sekarang->subDays(2)->toDateString(), $sekarang->subDay()->toDateString(), $hariIni])
                ->get()
                ->filter(fn (Sesi $s) => ! isset($libur[$s->tanggal])
                    && ($s->tanggal < $hariIni || $sekarang->format('H:i:s') > $s->datang_selesai));

            foreach ($sesis as $sesi) {
                $total += $this->tandai($program, $sesi, $simulasi);
            }
        }

        $this->info(($simulasi ? '[SIMULASI] ' : '')."{$total} kehadiran ditandai Alpa.");

        return self::SUCCESS;
    }

    private function tandai(Program $program, Sesi $sesi, bool $simulasi): int
    {
        $query = CardFormSubmission::acrossPenyelenggara()
            ->where('program_id', $program->id)
            ->whereNull('archived_at')
            ->whereDoesntHave('kehadirans', fn ($q) => $q->where('sesi_id', $sesi->id));

        if ($sesi->grup_id) {
            $query->whereHas('grups', fn ($q) => $q->withoutGlobalScope('penyelenggara')->whereIn('grups.id', Grup::denganTurunan($program->id, [$sesi->grup_id])));
        }

        $baris = $query->get(['id', 'created_at'])
            ->filter(fn (CardFormSubmission $p) => $p->created_at->setTimezone($program->timezone)->toDateString() <= $sesi->tanggal)
            ->map(fn (CardFormSubmission $p) => [
                'id' => (string) Str::ulid(),
                'penyelenggara_id' => $program->penyelenggara_id,
                'program_id' => $program->id,
                'sesi_id' => $sesi->id,
                'card_form_submission_id' => $p->id,
                'status' => AttendanceStatus::Alpa->value,
                'sumber' => Kehadiran::SUMBER_OTOMATIS,
                'created_at' => now(),
                'updated_at' => now(),
            ])->values()->all();

        if (! $simulasi && $baris) {
            Kehadiran::acrossPenyelenggara()->insertOrIgnore($baris);
        }

        return count($baris);
    }
}
