<?php

namespace App\Services\Penyelenggara;

use App\Models\Kategori;
use App\Models\Program;
use App\Models\Sesi;
use App\Models\SesiPola;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menurunkan pola menjadi sesi per tanggal (spec 003).
 *
 * Sesi yang sudah lewat tidak pernah disentuh; sesi hari ini yang sudah punya
 * kehadiran hanya diubah bila admin mengonfirmasi (`$termasukHariIni`).
 */
class SesiGenerator
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function simpan(Program $program, array $data, ?SesiPola $pola = null, bool $termasukHariIni = false): SesiPola
    {
        $data = $this->normalisasi($program, $data);

        $calon = new SesiPola(array_merge($data, ['program_id' => $program->id, 'penyelenggara_id' => $program->penyelenggara_id]));
        $tanggal = $calon->tanggalBerlaku($program);

        if ($tanggal === []) {
            throw ValidationException::withMessages(['hari' => 'Pola ini tidak menghasilkan sesi di dalam rentang tanggal program. Periksa hari atau tanggalnya.']);
        }

        $this->tolakTabrakan($program, $calon, $tanggal, $pola);

        return DB::transaction(function () use ($pola, $calon, $program, $termasukHariIni) {
            if ($pola) {
                $pola->update($calon->only($calon->getFillable()));
            } else {
                $pola = $calon;
                $pola->save();
            }

            $this->sinkronPola($program, $pola, $termasukHariIni);

            return $pola;
        });
    }

    public function hapus(Program $program, SesiPola $pola): void
    {
        DB::transaction(function () use ($program, $pola) {
            $this->sesiMendatang($program, $pola)->each(fn (Sesi $s) => $s->punyaKehadiran() ? null : $s->delete());
            $pola->delete();
        });
    }

    /**
     * Samakan semua sesi mendatang program dengan polanya (mis. setelah tanggal program diubah).
     */
    public function sinkronProgram(Program $program): void
    {
        SesiPola::acrossPenyelenggara()->where('program_id', $program->id)->get()
            ->each(fn (SesiPola $pola) => $this->sinkronPola($program, $pola, false));
    }

    public function sinkronPola(Program $program, SesiPola $pola, bool $termasukHariIni): void
    {
        $hariIni = CarbonImmutable::now($program->timezone)->toDateString();
        $target = array_flip($pola->tanggalBerlaku($program));
        $ada = Sesi::acrossPenyelenggara()->where('sesi_pola_id', $pola->id)->get()->keyBy(fn (Sesi $s) => $s->tanggal);

        foreach ($ada as $tanggal => $sesi) {
            if ($tanggal < $hariIni) {
                continue;
            }
            if ($tanggal === $hariIni && ! $termasukHariIni && $sesi->punyaKehadiran()) {
                continue;
            }
            if (! isset($target[$tanggal])) {
                $sesi->punyaKehadiran() ? null : $sesi->delete();

                continue;
            }
            $sesi->update(['name' => $pola->name, 'grup_id' => $pola->grup_id] + $pola->jendela());
        }

        foreach (array_keys($target) as $tanggal) {
            if ($tanggal < $hariIni || $ada->has($tanggal)) {
                continue;
            }
            Sesi::create([
                'penyelenggara_id' => $program->penyelenggara_id,
                'program_id' => $program->id,
                'sesi_pola_id' => $pola->id,
                'grup_id' => $pola->grup_id,
                'name' => $pola->name,
                'tanggal' => $tanggal,
            ] + $pola->jendela());
        }
    }

    /**
     * Sesi lain pada tanggal yang sama tidak boleh beririsan bila targetnya sama
     * atau salah satunya seluruh program (resolver scan harus deterministik).
     *
     * @param  array<int, string>  $tanggal
     */
    private function tolakTabrakan(Program $program, SesiPola $calon, array $tanggal, ?SesiPola $abaikan): void
    {
        $mulai = $calon->datang_mulai;
        $akhir = $calon->pulang_selesai ?? $calon->datang_selesai;

        $bentrok = Sesi::acrossPenyelenggara()
            ->where('program_id', $program->id)
            ->whereIn('tanggal', $tanggal)
            ->when($abaikan, fn ($q) => $q->where(fn ($w) => $w->whereNull('sesi_pola_id')->orWhere('sesi_pola_id', '!=', $abaikan->id)))
            ->when($calon->grup_id, fn ($q) => $q->where(fn ($w) => $w->whereNull('grup_id')->orWhere('grup_id', $calon->grup_id)))
            ->where('datang_mulai', '<', $akhir)
            ->whereRaw('COALESCE(pulang_selesai, datang_selesai) > ?', [$mulai])
            ->orderBy('tanggal')
            ->first();

        if ($bentrok) {
            $jam = substr($bentrok->datang_mulai, 0, 5).'–'.substr($bentrok->selesai(), 0, 5);
            throw ValidationException::withMessages([
                'datang_mulai' => "Bertabrakan dengan {$bentrok->name} ({$jam}) pada ".CarbonImmutable::parse($bentrok->tanggal)->locale('id')->translatedFormat('j F Y').'.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function normalisasi(Program $program, array $data): array
    {
        $kategori = Kategori::acrossPenyelenggara()->find($program->kategori_id);
        $jam = fn (?string $v) => $v ? substr($v, 0, 5).':00' : null;

        foreach (SesiPola::JENDELA as $kolom) {
            $data[$kolom] = $jam($data[$kolom] ?? null);
        }

        if (! $kategori?->fitur('terlambat')) {
            $data['batas_terlambat'] = null;
        }
        if (! $kategori?->fitur('pulang')) {
            $data['pulang_mulai'] = $data['pulang_selesai'] = null;
        }

        $salah = [];
        if (! $data['datang_mulai'] || ! $data['datang_selesai'] || $data['datang_mulai'] >= $data['datang_selesai']) {
            $salah['datang_selesai'] = 'Jam akhir datang harus setelah jam mulai datang (sesi lintas tengah malam belum didukung).';
        }
        if ($data['batas_terlambat'] && ($data['batas_terlambat'] < $data['datang_mulai'] || $data['batas_terlambat'] > $data['datang_selesai'])) {
            $salah['batas_terlambat'] = 'Batas terlambat harus berada di dalam jendela datang.';
        }
        if ($kategori?->fitur('pulang')) {
            if (! $data['pulang_mulai'] || ! $data['pulang_selesai'] || $data['pulang_mulai'] >= $data['pulang_selesai']) {
                $salah['pulang_selesai'] = 'Isi jendela pulang dengan jam akhir setelah jam mulai.';
            } elseif ($data['pulang_mulai'] < $data['datang_selesai']) {
                $salah['pulang_mulai'] = 'Jendela pulang harus dimulai setelah jendela datang berakhir.';
            }
        }
        if (($data['pengulangan'] ?? null) === SesiPola::MINGGUAN && empty($data['hari'])) {
            $salah['hari'] = 'Pilih minimal satu hari.';
        }
        if (($data['pengulangan'] ?? null) === SesiPola::SEKALI && empty($data['tanggal'])) {
            $salah['tanggal'] = 'Pilih tanggal sesi.';
        }

        if ($salah) {
            throw ValidationException::withMessages($salah);
        }

        return $data;
    }

    /**
     * @return Collection<int, Sesi>
     */
    private function sesiMendatang(Program $program, SesiPola $pola)
    {
        return Sesi::acrossPenyelenggara()
            ->where('sesi_pola_id', $pola->id)
            ->where('tanggal', '>=', CarbonImmutable::now($program->timezone)->toDateString())
            ->get();
    }
}
