<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Halaman album dan ZIP-nya dibuat ulang setiap kali admin mengunduh — tidak
 * ada baris database yang menunjuknya dan tidak pernah dibaca lagi. Tanpa
 * pembersihan ini folder `albums/` hanya bertambah (1,5 GB di produksi pada
 * Oktober 2026).
 */
class PruneAlbumFiles extends Command
{
    protected $signature = 'albums:prune {--days=2 : Hapus berkas album yang lebih tua dari sekian hari}';

    protected $description = 'Hapus halaman dan ZIP album sementara yang sudah lama.';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $batas = now()->subDays(max(1, (int) $this->option('days')))->getTimestamp();
        $jumlah = 0;
        $ukuran = 0;

        foreach ($disk->allFiles('albums') as $berkas) {
            if ($disk->lastModified($berkas) >= $batas) {
                continue;
            }

            $ukuran += $disk->size($berkas);
            $disk->delete($berkas);
            $jumlah++;
        }

        $this->info(sprintf('Menghapus %d berkas album (%.1f MB).', $jumlah, $ukuran / 1048576));

        return self::SUCCESS;
    }
}
