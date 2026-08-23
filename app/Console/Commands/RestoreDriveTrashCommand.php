<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\GoogleDriveService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Kembalikan berkas dari sampah Drive.
 *
 * Pasangan `drive:bersihkan-duplikat`. Sampah Drive menyimpan 30 hari, dan
 * memulihkan lewat UI satu per satu tidak masuk akal untuk ratusan berkas.
 *
 * Dua penyaring yang membuatnya bisa menyasar tepat sasaran:
 *
 *   --cocok=17357        hanya berkas yang namanya mengandung potongan itu
 *   --sejak=2026-08-23   hanya yang dibuang pada atau sesudah waktu itu
 *
 * Tanpa satu pun penyaring ia akan memulihkan SELURUH isi sampah, termasuk
 * berkas yang memang sengaja dibuang orang jauh sebelumnya — karena itu tanpa
 * penyaring ia menolak jalan kecuali diberi `--semua`.
 */
class RestoreDriveTrashCommand extends Command
{
    protected $signature = 'drive:pulihkan-sampah
                            {--dry-run : Laporkan saja, tidak menyentuh Drive}
                            {--school= : Batasi ke satu school_id}
                            {--cocok= : Hanya berkas yang namanya mengandung potongan ini}
                            {--sejak= : Hanya yang dibuang pada atau sesudah waktu ini, mis. 2026-08-23}
                            {--semua : Wajib ketika tidak ada penyaring sama sekali}';

    protected $description = 'Kembalikan berkas dari sampah Drive ke folder asalnya';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $prefix = $dryRun ? '[dry-run] ' : '';
        $cocok = $this->option('cocok');
        $sejak = $this->option('sejak');

        if (! $cocok && ! $sejak && ! $this->option('semua')) {
            $this->error('Tanpa --cocok atau --sejak, perintah ini akan memulihkan SELURUH isi sampah Drive.');
            $this->line('Kalau memang itu yang kamu mau, tambahkan --semua.');

            return self::FAILURE;
        }

        $schools = School::with('driveConfig')
            ->when($this->option('school'), fn ($query, $id) => $query->where('id', $id))
            ->get();

        $dipulihkan = 0;
        $diperiksa = 0;

        foreach ($schools as $school) {
            $config = $school->driveConfig;

            if (! $config || ! $config->is_active) {
                continue;
            }

            if (! GoogleDriveService::hasGlobalCredentials() && ! $config->service_account_json) {
                continue;
            }

            try {
                $drive = GoogleDriveService::forSchool($config);
                $sampah = $drive->trashedFiles();
            } catch (Throwable $e) {
                $this->error("{$school->name}: gagal membaca sampah — {$e->getMessage()}");

                continue;
            }

            $diperiksa += count($sampah);

            foreach (self::saring($sampah, $cocok, $sejak) as $berkas) {
                $waktu = $berkas['trashedTime'] ?? 'entah kapan';
                $this->line("{$prefix}{$school->name}: {$berkas['name']} (dibuang {$waktu})");

                if (! $dryRun) {
                    $drive->untrashFile($berkas['id']);
                }

                $dipulihkan++;
            }
        }

        $this->newLine();
        $this->info("{$prefix}Isi sampah dibaca: {$diperiksa}, dipulihkan: {$dipulihkan}.");

        if ($dryRun) {
            $this->line('Tidak ada berkas yang disentuh. Jalankan tanpa --dry-run untuk menerapkan.');
        }

        return self::SUCCESS;
    }

    /**
     * Saring isi sampah menurut potongan nama dan waktu pembuangan.
     *
     * Perbandingan waktunya string ISO 8601 apa adanya — Drive selalu
     * mengembalikan UTC berformat tetap, jadi urutan leksikalnya sama dengan
     * urutan kronologisnya, dan `--sejak=2026-08-23` cukup sebagai awalan hari.
     *
     * Berkas tanpa `trashedTime` ikut lolos ketika --sejak dipakai: umurnya
     * tidak diketahui, dan pada pemulihan lebih aman kelebihan daripada
     * ketinggalan.
     *
     * Public dan statis supaya bisa diuji tanpa menyentuh Drive.
     *
     * @param  array<int, array<string, mixed>>  $files
     * @return array<int, array<string, mixed>>
     */
    public static function saring(array $files, ?string $cocok, ?string $sejak): array
    {
        return array_values(array_filter($files, static function (array $file) use ($cocok, $sejak): bool {
            if ($cocok !== null && $cocok !== '' && ! str_contains(mb_strtolower($file['name']), mb_strtolower($cocok))) {
                return false;
            }

            if ($sejak !== null && $sejak !== '') {
                $dibuang = $file['trashedTime'] ?? null;

                if ($dibuang !== null && $dibuang < $sejak) {
                    return false;
                }
            }

            return true;
        }));
    }
}
