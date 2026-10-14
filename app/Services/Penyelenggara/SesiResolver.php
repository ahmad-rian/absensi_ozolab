<?php

namespace App\Services\Penyelenggara;

use App\Models\CardFormSubmission;
use App\Models\Grup;
use App\Models\Libur;
use App\Models\Program;
use App\Models\Sesi;
use Carbon\CarbonImmutable;

/**
 * Sesi yang berlaku untuk satu peserta pada satu waktu (spec 003 FR-007).
 *
 * Lintas tenant secara sengaja: dipakai rute scan publik yang tidak punya
 * konteks penyelenggara — batasnya adalah program milik peserta itu sendiri.
 */
class SesiResolver
{
    /**
     * @return array{sesi: ?Sesi, berikutnya: ?Sesi, libur: ?Libur, sekarang: CarbonImmutable}
     */
    public function untuk(CardFormSubmission $peserta, ?CarbonImmutable $waktu = null): array
    {
        $program = Program::acrossPenyelenggara()->findOrFail($peserta->program_id);
        $sekarang = ($waktu ?? CarbonImmutable::now())->setTimezone($program->timezone);
        $tanggal = $sekarang->toDateString();
        $jam = $sekarang->format('H:i:s');

        $libur = Libur::acrossPenyelenggara()->where('program_id', $program->id)->where('tanggal', $tanggal)->first();
        if ($libur) {
            return ['sesi' => null, 'berikutnya' => null, 'libur' => $libur, 'sekarang' => $sekarang];
        }

        $grupIds = $this->grupDanLeluhur($program, $peserta);

        $sesis = Sesi::acrossPenyelenggara()
            ->where('program_id', $program->id)
            ->where('tanggal', $tanggal)
            ->where(fn ($q) => $q->whereNull('grup_id')->orWhereIn('grup_id', $grupIds ?: ['-']))
            ->orderBy('datang_mulai')
            ->get();

        $aktif = $sesis->first(fn (Sesi $s) => $s->datang_mulai <= $jam && $jam <= $s->selesai());
        $berikutnya = $sesis->first(fn (Sesi $s) => $s->datang_mulai > $jam);

        return ['sesi' => $aktif, 'berikutnya' => $berikutnya, 'libur' => null, 'sekarang' => $sekarang];
    }

    /**
     * Grup peserta beserta semua leluhurnya — sesi untuk Kloter 12 berlaku bagi
     * jamaah di Rombongan 3 (anak Kloter 12).
     *
     * @return array<int, string>
     */
    private function grupDanLeluhur(Program $program, CardFormSubmission $peserta): array
    {
        $induk = Grup::acrossPenyelenggara()->where('program_id', $program->id)->pluck('parent_id', 'id');
        $hasil = [];

        foreach ($peserta->grups()->withoutGlobalScope('penyelenggara')->pluck('grups.id') as $id) {
            while ($id && ! isset($hasil[$id])) {
                $hasil[$id] = true;
                $id = $induk[$id] ?? null;
            }
        }

        return array_keys($hasil);
    }
}
