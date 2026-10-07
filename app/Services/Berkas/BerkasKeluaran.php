<?php

namespace App\Services\Berkas;

use App\Models\CardGenerationLog;
use App\Models\School;
use App\Services\GoogleDriveService;
use App\Support\GambarCetak;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Kartu dan lembar pas foto hasil generate: Google Drive tempat simpannya,
 * disk server hanya persinggahan sampai unggahan terbukti utuh.
 *
 * Semua tampilan dan unduhan lewat {@see self::url()} — satu jalur yang
 * membaca disk kalau berkasnya masih ada, dan Drive kalau sudah tidak.
 * Foto siswa TIDAK lewat sini: foto dibaca perender kartu dan layar gerbang,
 * jadi salinan lokalnya tetap wajib.
 */
class BerkasKeluaran
{
    /** Jenis log yang salinan lokalnya boleh dibuang setelah masuk Drive. */
    public const JENIS_DRIVE_SAJA = ['card', 'photo_sheet'];

    public const BERLAKU_JAM = 12;

    /** URL bertanda tangan untuk <img> atau unduhan, atau null kalau tidak ada berkasnya. */
    public function url(?CardGenerationLog $log, bool $unduh = false): ?string
    {
        if (! $log || (! $log->file_path && ! $log->drive_file_id)) {
            return null;
        }

        return URL::temporarySignedRoute(
            'berkas-keluaran',
            now()->addHours(self::BERLAKU_JAM),
            array_filter(['log' => $log->id, 'unduh' => $unduh ? 1 : null]),
        );
    }

    public function respons(CardGenerationLog $log, bool $unduh): BinaryFileResponse
    {
        $disk = Storage::disk('public');
        $sementara = false;

        if ($log->file_path && $disk->exists($log->file_path)) {
            $path = $disk->path($log->file_path);
        } else {
            abort_unless($log->drive_file_id, 404, 'Berkas belum tersedia.');
            $path = $this->unduhDariDrive($log);
            $sementara = true;
        }

        $nama = basename($log->file_path ?: 'berkas-'.$log->id.'.'.GambarCetak::EKSTENSI);
        $kepala = ['Content-Type' => GambarCetak::mime($nama), 'Cache-Control' => 'private, max-age=3600'];
        $respons = $unduh ? response()->download($path, $nama, $kepala) : response()->file($path, $kepala);

        return $respons->deleteFileAfterSend($sementara);
    }

    /**
     * Buang salinan lokal kartu/lembar pas foto setelah Drive memegang
     * salinan yang ukurannya sama persis. Gagal memeriksa = berkas lokal
     * dibiarkan; lebih baik disk penuh daripada kartu hilang.
     */
    public function buangLokal(CardGenerationLog $log, GoogleDriveService $drive): bool
    {
        $disk = Storage::disk('public');

        if (! in_array($log->type, self::JENIS_DRIVE_SAJA, true)
            || ! $log->drive_file_id || ! $log->file_path || ! $disk->exists($log->file_path)) {
            return false;
        }

        try {
            if ($drive->ukuranBerkas($log->drive_file_id) !== $disk->size($log->file_path)) {
                Log::warning('Ukuran berkas Drive berbeda, salinan lokal dipertahankan', ['log_id' => $log->id]);

                return false;
            }
        } catch (\Throwable $e) {
            Log::warning('Tidak bisa memeriksa berkas Drive, salinan lokal dipertahankan', ['log_id' => $log->id, 'error' => $e->getMessage()]);

            return false;
        }

        return $disk->delete($log->file_path);
    }

    private function unduhDariDrive(CardGenerationLog $log): string
    {
        $config = School::with('driveConfig')->find($log->school_id)?->driveConfig;
        abort_unless($config, 404, 'Berkas belum tersedia.');
        $path = tempnam(sys_get_temp_dir(), 'berkas-');

        try {
            app(GoogleDriveService::class, ['config' => $config])->downloadFile($log->drive_file_id, $path);
        } catch (\Throwable $e) {
            @unlink($path);
            report($e);
            abort(503, 'Berkas dari Google Drive belum dapat diambil. Coba lagi nanti.');
        }

        return $path;
    }
}
