<?php

namespace App\Console\Commands\Concerns;

use App\Services\GoogleDriveService;
use App\Support\XlsxDownload;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Tampilan bersama perintah perawatan Drive (`drive:*`).
 *
 * Perintah-perintah ini menyapu puluhan ribu berkas dan ribuan siswa; tanpa
 * bar progres mereka diam bermenit-menit dan tampak macet. Hasil lengkapnya
 * disimpan sebagai XLSX karena ribuan baris tidak muat di terminal — dan XLSX,
 * bukan CSV, supaya NIS berawalan nol tidak dirusak Excel.
 */
trait TampilanPerawatanDrive
{
    private ?ProgressBar $barAktif = null;

    /** @var array<int, array<int, string>> setiap tindakan, untuk laporan lengkap */
    private array $tindakan = [];

    /** Sapuan seluruh berkas Drive dengan penghitung berjalan. */
    protected function sapuBerkas(GoogleDriveService $drive, string $label): array
    {
        $bar = $this->output->createProgressBar();
        $bar->setFormat(" {$label}: menyapu berkas Drive… %current% berkas terbaca · %elapsed:6s%");
        $bar->start();

        $hasil = $drive->filesByParent(fn (int $terbaca) => $bar->setProgress($terbaca));

        $bar->finish();
        $this->newLine();

        return $hasil;
    }

    /** Daftar folder siswa, dengan bar per folder kelas. */
    protected function bacaFolderSiswa(GoogleDriveService $drive, string $label): array
    {
        $bar = null;

        $hasil = $drive->studentFolders(function (int $selesai, int $total) use (&$bar, $label): void {
            if ($bar === null) {
                $bar = $this->output->createProgressBar($total);
                $bar->setFormat(" {$label}: membaca folder kelas %current%/%max% [%bar%] %percent:3s%%");
                $bar->start();
            }

            $bar->setProgress($selesai);
        });

        if ($bar !== null) {
            $bar->finish();
            $this->newLine();
        }

        return $hasil;
    }

    /**
     * Bar per sekolah, atau null bila tidak ada yang diproses — bar bertotal 0
     * tidak bisa menghitung sisa waktu dan melempar LogicException.
     */
    protected function mulaiBar(string $label, int $total): ?ProgressBar
    {
        if ($total === 0) {
            $this->line(" {$label}: tidak ada yang diproses, dilewati.");

            return null;
        }

        $this->barAktif = $this->output->createProgressBar($total);
        $this->barAktif->setFormat(" {$label}: %current%/%max% [%bar%] %percent:3s%% · sisa ~%remaining:6s%");
        $this->barAktif->start();

        return $this->barAktif;
    }

    protected function majukanBar(): void
    {
        $this->barAktif?->advance();
    }

    protected function selesaikanBar(): void
    {
        if ($this->barAktif !== null) {
            $this->barAktif->finish();
            $this->newLine();
            $this->barAktif = null;
        }
    }

    /** Cetak satu baris tanpa merusak bar yang sedang berjalan. */
    protected function cetak(string $pesan, string $gaya = 'line'): void
    {
        $this->barAktif?->clear();
        $this->{$gaya}($pesan);
        $this->barAktif?->display();
    }

    /** Cetak satu tindakan dan simpan untuk laporan lengkap. */
    protected function catat(string $sekolah, string $subjek, string $jenis, string $rincian, string $pesan, string $gaya = 'line'): void
    {
        $this->tindakan[] = [$sekolah, $subjek, $jenis, $rincian];
        $this->cetak($pesan, $gaya);
    }

    /** Simpan semua tindakan yang tercatat; null kalau tidak ada. */
    protected function simpanTindakan(string $nama, bool $simulasi): ?string
    {
        if ($this->tindakan === []) {
            return null;
        }

        return $this->simpanLaporan($nama.($simulasi ? '-simulasi' : ''), [
            'Tindakan' => ['header' => ['Sekolah', 'Siswa / folder', 'Tindakan', 'Rincian'], 'rows' => $this->tindakan],
        ]);
    }

    /**
     * Simpan hasil lengkap ke `storage/app/private/laporan-drive/` dan kembalikan
     * letaknya.
     *
     * @param  array<string, array{header: array<int, string>, rows: array<int, array<int, mixed>>}>  $sheets
     */
    protected function simpanLaporan(string $nama, array $sheets): string
    {
        $relatif = 'laporan-drive/'.$nama.'-'.now()->format('Ymd-His').'.xlsx';
        $disk = Storage::disk('local');
        $disk->makeDirectory('laporan-drive');
        XlsxDownload::tulis($disk->path($relatif), $sheets);

        return $disk->path($relatif);
    }
}
