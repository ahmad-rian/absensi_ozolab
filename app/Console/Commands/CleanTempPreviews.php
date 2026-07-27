<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class CleanTempPreviews extends Command
{
    protected $signature = 'temp:clean {--minutes=60 : Delete temp files older than this many minutes}';

    protected $description = 'Purge stale photo-preview files (private registration previews + legacy public temp).';

    public function handle(): int
    {
        $cutoff = now()->subMinutes((int) $this->option('minutes'))->getTimestamp();
        $deleted = 0;

        // Lokasi baru: pratinjau pendaftaran disimpan di disk privat.
        $deleted += $this->purge(Storage::disk('local'), 'registration-previews', $cutoff);

        // Lokasi lama masih dibersihkan supaya sisa berkas produksi ikut hilang.
        $deleted += $this->purge(Storage::disk('public'), 'temp', $cutoff);

        $this->info("Deleted {$deleted} stale preview file(s).");

        return self::SUCCESS;
    }

    private function purge(Filesystem $disk, string $directory, int $cutoff): int
    {
        if (! $disk->directoryExists($directory)) {
            return 0;
        }

        $deleted = 0;

        foreach ($disk->files($directory) as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}
