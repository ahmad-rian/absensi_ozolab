<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filter daftar peserta per kolom data (mis. per kecamatan — padanan "per
 * kelas" pada siswa). Kolom yang bisa difilter: bertipe pilihan, bertipe
 * kecamatan, atau kolom data lama yang kunci/labelnya memuat "kecamatan".
 *
 * Kunci kolom selalu dicocokkan dengan daftar kolom yang sah sebelum dipakai
 * di jalur JSON (`data->kunci`), jadi masukan pengguna tidak pernah langsung
 * menjadi bagian query.
 */
final class FilterKolomPeserta
{
    private const MAKS_OPSI = 300;

    /** Kelompok laporan untuk peserta yang kolom kecamatannya kosong. */
    public const TANPA_KECAMATAN = 'Tanpa kecamatan';

    /**
     * Kunci kolom kecamatan sebuah skema: kolom bertipe `kecamatan` dulu,
     * lalu kolom data lama yang kunci/labelnya memuat "kecamatan".
     *
     * @param  iterable<int, array<string, mixed>>  $fields
     */
    public static function kolomKecamatan(iterable $fields): ?string
    {
        $cadangan = null;
        foreach ($fields as $f) {
            $key = (string) ($f['key'] ?? '');
            if ($key === '' || ! preg_match('/^[a-z0-9_]+$/', $key)) {
                continue;
            }
            if (($f['type'] ?? '') === 'kecamatan') {
                return $key;
            }
            if ($cadangan === null && str_contains(strtolower($key.' '.($f['label'] ?? '')), 'kecamatan')) {
                $cadangan = $key;
            }
        }

        return $cadangan;
    }

    /**
     * Nama kecamatan untuk pengelompokan: bagian sebelum koma, jadi isian lama
     * "Purwokerto Barat" dan "Purwokerto Barat, Banyumas" masuk kelompok sama.
     * Kosong menjadi {@see self::TANPA_KECAMATAN}.
     */
    public static function namaKecamatan(mixed $nilai): string
    {
        $teks = trim(explode(',', is_scalar($nilai) ? (string) $nilai : '')[0]);
        $teks = (string) preg_replace('/^kec(amatan)?\.?\s+/iu', '', $teks);
        $teks = (string) preg_replace('/\s+/u', ' ', $teks);

        return $teks === '' ? self::TANPA_KECAMATAN : mb_convert_case(mb_strtolower($teks), MB_CASE_TITLE);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function kecamatanPeserta(?array $data, ?string $kunci): string
    {
        return self::namaKecamatan($kunci ? ($data[$kunci] ?? null) : null);
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $fields
     * @return array<string, array{key: string, label: string, type: string, options: array<int, string>}>
     */
    public static function kolom(iterable $fields): array
    {
        $hasil = [];
        foreach ($fields as $f) {
            $key = (string) ($f['key'] ?? '');
            $tipe = (string) ($f['type'] ?? 'text');
            $kecamatan = str_contains(strtolower($key.' '.($f['label'] ?? '')), 'kecamatan');

            if ($key === '' || isset($hasil[$key]) || ! preg_match('/^[a-z0-9_]+$/', $key)) {
                continue;
            }
            if ($tipe === 'select' || $tipe === 'kecamatan' || $kecamatan) {
                $hasil[$key] = [
                    'key' => $key,
                    'label' => (string) ($f['label'] ?? $key),
                    'type' => $tipe,
                    'options' => $tipe === 'select' ? array_values($f['options'] ?? []) : [],
                ];
            }
        }

        return $hasil;
    }

    /**
     * Lengkapi opsi kolom non-pilihan dengan nilai yang benar-benar ada di data.
     *
     * @param  array<string, array{key: string, label: string, type: string, options: array<int, string>}>  $kolom
     * @param  Builder<Model>  $sumber
     * @return array<int, array{key: string, label: string, options: array<int, string>}>
     */
    public static function denganOpsi(array $kolom, Builder $sumber): array
    {
        $perlu = array_keys(array_filter($kolom, fn ($k) => $k['options'] === []));
        $nilai = array_fill_keys($perlu, []);

        if ($perlu !== []) {
            $sumber->select('data')->orderBy('id')->chunk(500, function ($baris) use (&$nilai, $perlu) {
                foreach ($baris as $b) {
                    foreach ($perlu as $key) {
                        $v = trim((string) ($b->data[$key] ?? ''));
                        if ($v !== '') {
                            $nilai[$key][$v] = true;
                        }
                    }
                }
            });
        }

        return array_values(array_map(function (array $k) use ($nilai) {
            $opsi = $k['options'] ?: array_keys($nilai[$k['key']] ?? []);
            natcasesort($opsi);

            return ['key' => $k['key'], 'label' => $k['label'], 'options' => array_slice(array_values($opsi), 0, self::MAKS_OPSI)];
        }, $kolom));
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $kolom
     * @param  array<string, mixed>  $pilihan  dari query string `f[kunci]=nilai`
     * @return array<string, string> filter yang benar-benar diterapkan
     */
    public static function terapkan(Builder $query, array $kolom, array $pilihan): array
    {
        $dipakai = [];
        foreach ($pilihan as $key => $v) {
            if (! is_string($key) || ! isset($kolom[$key]) || ! is_string($v) || $v === '') {
                continue;
            }
            $query->where('data->'.$key, $v);
            $dipakai[$key] = $v;
        }

        return $dipakai;
    }
}
