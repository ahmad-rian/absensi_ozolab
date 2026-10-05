<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Baris laporan siswa yang dipilah per kelas, untuk Excel dan PDF.
 *
 * Wali kelas mencetak dan membagikan laporan per kelas; satu tabel panjang
 * berisi seluruh sekolah harus dipotong-potong sendiri di Excel.
 */
class KelompokKelas
{
    public const TANPA_KELAS = 'Tanpa Kelas';

    /**
     * Urut nama kelas secara alami (7 A, 8 A, 10 A — bukan 10 A, 7 A),
     * siswa tanpa kelas paling akhir. Urutan siswa di dalam kelas dipertahankan.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    public static function dari(Collection $rows): Collection
    {
        $kelompok = $rows->groupBy(
            fn (array $row): string => in_array($row['classroom_name'] ?? null, [null, '', '-'], true)
                ? self::TANPA_KELAS
                : (string) $row['classroom_name'],
        );

        $nama = $kelompok->keys()->reject(fn (string $kelas): bool => $kelas === self::TANPA_KELAS)->all();
        natcasesort($nama);

        if ($kelompok->has(self::TANPA_KELAS)) {
            $nama[] = self::TANPA_KELAS;
        }

        return collect($nama)->mapWithKeys(fn (string $kelas): array => [$kelas => $kelompok[$kelas]->values()]);
    }
}
