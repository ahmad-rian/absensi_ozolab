<?php

namespace App\Console\Commands;

use App\Models\CardGenerationLog;
use Illuminate\Console\Command;

/**
 * Isi `drive_file_id` yang kosong dari `drive_url`.
 *
 * Sebelum c16d197 (26 Agustus), `GenerateRegistrationCardJob` hanya menyimpan
 * `drive_url`. Tanpa id, `berkas:pindah-ke-drive` melewatkan baris itu dan
 * salinan lokalnya tidak pernah dibersihkan. Id-nya sudah ada di dalam URL.
 */
class IsiDriveFileIdCommand extends Command
{
    protected $signature = 'berkas:isi-drive-id
        {--jalankan : Benar-benar menulis; tanpa ini hanya simulasi}';

    protected $description = 'Isi drive_file_id kartu & lembar pas foto yang kosong dari drive_url';

    public function handle(): int
    {
        $jalankan = (bool) $this->option('jalankan');
        $terisi = 0;
        $tanpaId = 0;

        CardGenerationLog::withoutGlobalScopes()
            ->whereNull('drive_file_id')
            ->whereNotNull('drive_url')
            ->eachById(function (CardGenerationLog $log) use ($jalankan, &$terisi, &$tanpaId): void {
                $id = self::idDariUrl($log->drive_url);

                if ($id === null) {
                    $tanpaId++;

                    return;
                }

                $terisi++;

                if ($jalankan) {
                    $log->forceFill(['drive_file_id' => $id])->saveQuietly();
                }
            });

        $this->info(sprintf(
            '%s %d baris. %d URL tanpa id berkas dilewati.',
            $jalankan ? 'Diisi' : 'Akan diisi',
            $terisi,
            $tanpaId,
        ));

        if (! $jalankan) {
            $this->warn('Simulasi saja. Jalankan ulang dengan --jalankan untuk menulis.');
        }

        return self::SUCCESS;
    }

    /**
     * Id berkas dari URL Drive: `/file/d/{id}/…` atau `?id={id}`.
     */
    public static function idDariUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (preg_match('~/file/d/([A-Za-z0-9_-]{10,})~', $url, $m) || preg_match('~[?&]id=([A-Za-z0-9_-]{10,})~', $url, $m)) {
            return $m[1];
        }

        return null;
    }
}
