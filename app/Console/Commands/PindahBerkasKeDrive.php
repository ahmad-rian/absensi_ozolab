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
        {--latest-only : Per siswa+jenis+layout, buang juga salinan lokal riwayat lama bila versi terbarunya ada di Drive}
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

            if ($this->option('latest-only')) {
                $this->terbaruSaja($s, $berkas, $drive, $jalankan, $hitung);
                $this->line("{$s->name}: selesai");

                continue;
            }

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

    /**
     * Riwayat lama yang tidak ikut dibersihkan jalur biasa.
     *
     * Jalur biasa hanya membuang berkas yang ukurannya sama persis dengan
     * Drive. Itu jarang benar untuk riwayat lama: generate ulang menimpa berkas
     * Drive yang sama di tempat, jadi log lama menunjuk berkas Drive yang kini
     * berisi render lebih baru, dan log dari sebelum Agustus tidak punya
     * `drive_file_id` sama sekali. Selama versi TERBARU sudah ada di Drive,
     * salinan lokal versi lama tidak lagi dibaca siapa pun — `/berkas/{log}`
     * mengambil dari Drive bila berkas lokalnya hilang.
     *
     * Versi terbaru sendiri tetap memakai aturan ukuran-sama-persis.
     *
     * @param  array{dibuang: int, dibiarkan: int, byte: int}  $hitung
     */
    private function terbaruSaja(School $sekolah, BerkasKeluaran $berkas, GoogleDriveService $drive, bool $jalankan, array &$hitung): void
    {
        $disk = Storage::disk('public');

        $kelompok = CardGenerationLog::withoutGlobalScopes()
            ->where('school_id', $sekolah->id)
            ->whereIn('type', BerkasKeluaran::JENIS_DRIVE_SAJA)
            ->whereNotNull('student_id')
            ->orderBy('student_id')
            ->orderBy('type')
            ->orderBy('school_card_layout_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'student_id', 'type', 'school_card_layout_id', 'file_path', 'drive_file_id', 'created_at'])
            ->groupBy(fn (CardGenerationLog $l) => $l->student_id.'|'.$l->type.'|'.$l->school_card_layout_id);

        foreach ($kelompok as $logs) {
            /** @var CardGenerationLog $terbaru */
            $terbaru = $logs->first();

            if (! $terbaru->drive_file_id || ! $this->adaDiDrive($drive, $terbaru->drive_file_id)) {
                continue;
            }

            if ($terbaru->file_path && $disk->exists($terbaru->file_path)) {
                $ukuran = $disk->size($terbaru->file_path);
                $cocok = $jalankan
                    ? $berkas->buangLokal(CardGenerationLog::withoutGlobalScopes()->find($terbaru->id), $drive)
                    : $this->ukuranSama($drive, $terbaru, $ukuran);
                $hitung[$cocok ? 'dibuang' : 'dibiarkan']++;
                $hitung['byte'] += $cocok ? $ukuran : 0;
            }

            foreach ($logs->slice(1) as $lama) {
                // Nama berkas yang sama dengan versi terbaru ADALAH berkas versi
                // terbaru — itu sudah diputuskan di atas, jangan disentuh lagi.
                if (! $lama->file_path || $lama->file_path === $terbaru->file_path || ! $disk->exists($lama->file_path)) {
                    continue;
                }

                $hitung['dibuang']++;
                $hitung['byte'] += $disk->size($lama->file_path);

                if ($jalankan) {
                    $disk->delete($lama->file_path);
                } else {
                    $this->line('  lama: '.$lama->file_path);
                }
            }
        }
    }

    private function adaDiDrive(GoogleDriveService $drive, string $fileId): bool
    {
        try {
            return $drive->ukuranBerkas($fileId) !== null;
        } catch (\Throwable) {
            return false;
        }
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
