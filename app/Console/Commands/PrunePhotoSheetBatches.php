<?php

namespace App\Console\Commands;

use App\Models\PhotoSheetBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * PDF lembar pas foto adalah berkas cetak sekali pakai, bukan arsip — salinan
 * permanennya tidak pernah dibuat. Tanpa pembersihan ini, satu studio yang
 * mencetak ribuan siswa akan memenuhi disk dengan PDF yang tidak pernah dibuka
 * lagi.
 */
class PrunePhotoSheetBatches extends Command
{
    protected $signature = 'photo-sheets:prune {--days=7 : Hapus batch yang lebih tua dari sekian hari}';

    protected $description = 'Hapus batch lembar pas foto lama beserta berkas PDF-nya.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $batches = PhotoSheetBatch::withoutGlobalScopes()
            ->where('created_at', '<', $cutoff)
            ->get();

        $files = 0;

        foreach ($batches as $batch) {
            if ($batch->file_path && Storage::disk('public')->exists($batch->file_path)) {
                Storage::disk('public')->delete($batch->file_path);
                $files++;
            }

            $batch->delete();
        }

        $this->info("Menghapus {$batches->count()} batch dan {$files} berkas PDF.");

        return self::SUCCESS;
    }
}
