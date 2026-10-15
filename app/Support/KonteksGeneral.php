<?php

namespace App\Support;

use App\Models\Kategori;
use App\Models\Penyelenggara;
use App\Models\Program;
use Illuminate\Http\Request;

/**
 * Konteks aktif workspace Absensi General (spec 009): "Haji (kartu lama)"
 * atau satu program di sebuah kategori. Dipilih lewat pemilih di kanan atas;
 * menu sidebar menyesuaikan pilihan ini.
 *
 * Halaman yang punya `{program}` di rutenya selalu menang atas session,
 * supaya membuka tautan program langsung tetap menampilkan konteks yang benar.
 */
class KonteksGeneral
{
    public const HAJI_LAMA = 'haji';

    public const SESI = 'general_konteks';

    /**
     * @return array{jenis: string, id: string, label: string, kategori: ?string, kategori_id: ?string, program_id: ?string, penyelenggara: ?string}
     */
    public static function aktif(Request $request): array
    {
        $program = $request->route('program');
        if (! $program instanceof Program) {
            $id = $request->session()->get(self::SESI, self::HAJI_LAMA);
            $program = $id !== self::HAJI_LAMA ? Program::acrossPenyelenggara()->find($id) : null;
        }

        if (! $program) {
            return ['jenis' => 'haji', 'id' => self::HAJI_LAMA, 'label' => 'Haji (kartu lama)', 'kategori' => null, 'kategori_id' => null, 'program_id' => null, 'penyelenggara' => null];
        }

        $kategori = Kategori::acrossPenyelenggara()->find($program->kategori_id);

        return [
            'jenis' => 'program',
            'id' => $program->id,
            'label' => $program->name,
            'kategori' => $kategori?->name,
            'kategori_id' => $program->kategori_id,
            'program_id' => $program->id,
            'penyelenggara' => Penyelenggara::find($program->penyelenggara_id)?->name,
        ];
    }

    /**
     * Pilihan konteks dikelompokkan per kategori · penyelenggara. Program
     * arsip tidak ditawarkan (masih bisa dibuka dari daftar Program).
     *
     * @return array<int, array{grup: string, opsi: array<int, array{id: string, label: string}>}>
     */
    public static function opsi(): array
    {
        $programs = Program::acrossPenyelenggara()
            ->whereNull('archived_at')
            ->orderByDesc('starts_on')
            ->get(['id', 'name', 'kategori_id', 'penyelenggara_id']);
        $kategoris = Kategori::acrossPenyelenggara()->whereIn('id', $programs->pluck('kategori_id'))->pluck('name', 'id');
        $penyelenggaras = Penyelenggara::whereIn('id', $programs->pluck('penyelenggara_id'))->pluck('name', 'id');

        $grup = [['grup' => 'Kartu lama', 'opsi' => [['id' => self::HAJI_LAMA, 'label' => 'Haji (kartu lama)']]]];

        foreach ($programs->groupBy(fn (Program $p) => $p->kategori_id) as $kategoriId => $daftar) {
            $grup[] = [
                'grup' => ($kategoris[$kategoriId] ?? 'Kategori').' · '.($penyelenggaras[$daftar->first()->penyelenggara_id] ?? '—'),
                'opsi' => $daftar->map(fn (Program $p) => ['id' => $p->id, 'label' => $p->name])->values()->all(),
            ];
        }

        return $grup;
    }
}
