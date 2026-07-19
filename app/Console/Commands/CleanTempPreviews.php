<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanTempPreviews extends Command
{
    protected $signature = 'temp:clean {--minutes=60 : Delete temp files older than this many minutes}';

    protected $description = 'Purge stale photo-preview temp files under storage/app/public/temp.';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $cutoff = now()->subMinutes((int) $this->option('minutes'))->getTimestamp();
        $deleted = 0;

        foreach ($disk->files('temp') as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $deleted++;
            }
        }

        $this->info("Deleted {$deleted} stale temp file(s).");

        return self::SUCCESS;
    }
}
