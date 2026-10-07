<?php

namespace App\Console\Commands;

use App\Models\CardGenerationLog;
use App\Models\School;
use App\Services\Berkas\BerkasKeluaran;
use App\Services\GoogleDriveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Buang salinan lokal kartu dan lembar pas foto yang Drive-nya sudah memegang
 * berkas berukuran sama persis. Tanpa `--jalankan` hanya menghitung.
 *
 * Berkas yang ukurannya di Drive berbeda (misalnya Drive sudah ditimpa versi
 * yang lebih baru) DIBIARKAN — perintah ini tidak pernah menebak.
 */
class PindahBerkasKeDrive extends Command
{
    protected $signature = 'berkas:pindah-ke-drive
        {--school= : Hanya satu sekolah (id)}
        {--jalankan : Benar-benar menghapus salinan lokal; tanpa ini hanya simulasi}';

    protected $description = 'Hapus salinan lokal kartu & lembar pas foto yang sudah utuh di Google Drive.';

    public function handle(BerkasKeluaran $berkas): int
    {
        $jalankan = (bool) $this->option('jalankan');
        $disk = Storage::disk('public');
        $hitung = ['dibuang' => 0, 'dibiarkan' => 0, 'byte' => 0];

        $sekolah = School::with('driveConfig')
            ->when($this->option('school'), fn ($q, $id) => $q->whereKey($id))
            ->get()
            ->filter(fn (School $s) => $s->driveConfig?->is_active);

        foreach ($sekolah as $s) {
            $drive = app(GoogleDriveService::class, ['config' => $s->driveConfig]);

            CardGenerationLog::withoutGlobalScopes()
                ->where('school_id', $s->id)
                ->whereIn('type', BerkasKeluaran::JENIS_DRIVE_SAJA)
                ->whereNotNull('drive_file_id')
                ->whereNotNull('file_path')
                ->eachById(function (CardGenerationLog $log) use ($berkas, $drive, $disk, $jalankan, &$hitung): void {
                    if (! $disk->exists($log->file_path)) {
                        return;
                    }

                    $ukuran = $disk->size($log->file_path);
                    $cocok = $jalankan
                        ? $berkas->buangLokal($log, $drive)
                        : $this->ukuranSama($drive, $log, $ukuran);

                    $hitung[$cocok ? 'dibuang' : 'dibiarkan']++;
                    $hitung['byte'] += $cocok ? $ukuran : 0;
                });

            $this->line("{$s->name}: selesai");
        }

        $this->info(sprintf(
            '%s %d berkas (%.1f MB). %d berkas dibiarkan karena ukurannya di Drive berbeda atau tidak bisa diperiksa.',
            $jalankan ? 'Dibuang' : 'Akan dibuang',
            $hitung['dibuang'],
            $hitung['byte'] / 1048576,
            $hitung['dibiarkan'],
        ));

        if (! $jalankan) {
            $this->warn('Simulasi saja. Jalankan ulang dengan --jalankan untuk menghapus.');
        }

        return self::SUCCESS;
    }

    private function ukuranSama(GoogleDriveService $drive, CardGenerationLog $log, int $ukuran): bool
    {
        try {
            return $drive->ukuranBerkas($log->drive_file_id) === $ukuran;
        } catch (\Throwable) {
            return false;
        }
    }
}
