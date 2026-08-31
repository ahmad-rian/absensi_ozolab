<?php

namespace App\Jobs;

use App\Models\School;
use App\Models\Student;
use App\Services\GoogleDriveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Buang folder Drive dan berkas lokal milik siswa yang sudah dihapus.
 *
 * Menerima NILAI, bukan model. Lewat jalur hapus orang tua, baris siswanya sudah
 * benar-benar lenyap dari database sebelum job ini sempat berjalan (cascade
 * tingkat DB), jadi job tidak boleh bergantung pada barisnya masih ada.
 *
 * Foldernya dibuang ke SAMPAH, bukan dihapus. Sampah Drive menyimpan 30 hari,
 * dan `drive:pulihkan-sampah` bisa mengembalikannya — memulihkan folder ikut
 * mengembalikan seluruh isinya. `GoogleDriveService::delete()` sengaja tidak
 * dipakai: ia satu-satunya penghapus permanen di kode ini dan tidak memberi
 * kesempatan membatalkan.
 */
class PurgeStudentDriveAssetsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    /**
     * @param  array<int, string>  $localPaths  berkas di disk `public` yang ikut dibuang
     */
    public function __construct(
        public string $schoolId,
        public string $studentId,
        public ?string $driveFolderId = null,
        public ?string $photoPath = null,
        public array $localPaths = [],
    ) {
        $this->onQueue(config('cards.queue'));
    }

    public function handle(): void
    {
        $this->purgeDrive();
        $this->purgeLocal();
    }

    private function purgeDrive(): void
    {
        if (! $this->driveFolderId) {
            return;
        }

        $config = School::with('driveConfig')->find($this->schoolId)?->driveConfig;

        if (! $config || ! $config->is_active) {
            return;
        }

        if (! GoogleDriveService::hasGlobalCredentials() && ! $config->service_account_json) {
            return;
        }

        // Satu folder bisa dipakai dua siswa: unique index `(school_id, nis,
        // deleted_at)` mengizinkan siswa aktif dan siswa terhapus memegang NIS
        // yang sama, dan kalau nama serta kelasnya juga sama, keduanya
        // menurunkan nama folder yang sama. Membuangnya berarti menghapus
        // berkas milik siswa yang MASIH AKTIF.
        if (! self::folderBolehDibuang($this->driveFolderId, $this->studentId)) {
            Log::warning('Folder Drive tidak dibuang: masih dipakai siswa lain', [
                'student_id' => $this->studentId,
                'drive_folder_id' => $this->driveFolderId,
            ]);

            return;
        }

        try {
            GoogleDriveService::forSchool($config)->trashFile($this->driveFolderId);
        } catch (Throwable $e) {
            // Siswanya sudah terhapus dan tidak akan kembali; Drive yang
            // bermasalah tidak boleh membuat job ini mengulang terus.
            Log::warning('Gagal membuang folder Drive siswa yang dihapus', [
                'student_id' => $this->studentId,
                'drive_folder_id' => $this->driveFolderId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Boleh dibuang, atau masih ada siswa hidup yang memakainya?
     *
     * Siswa yang di-soft-delete sengaja TIDAK dihitung — SoftDeletes sudah
     * membuangnya dari query ini. Kalau ia ikut dihitung, folder tidak akan
     * pernah bisa dibuang sama sekali: siswa yang barusan dihapus itu sendiri
     * masih punya baris yang menunjuk ke sana.
     *
     * Public dan statis supaya keputusannya bisa diuji tanpa memalsukan seluruh
     * klien Google — pola yang sama dengan `CleanDriveDuplicatesCommand::duplikat()`.
     */
    public static function folderBolehDibuang(?string $folderId, string $studentId): bool
    {
        if (! $folderId) {
            return false;
        }

        return ! Student::withoutGlobalScope('school')
            ->where('drive_folder_id', $folderId)
            ->where('id', '!=', $studentId)
            ->exists();
    }

    /**
     * Foto crop dan kartu PNG di disk `public`.
     *
     * Sampai sekarang tidak ada satu pun jalur yang membersihkannya, jadi tiap
     * siswa yang dihapus meninggalkan berkasnya selamanya — dan disk penuh
     * sudah pernah menjatuhkan seluruh server.
     */
    private function purgeLocal(): void
    {
        $disk = Storage::disk('public');

        foreach (array_filter([$this->photoPath, ...$this->localPaths]) as $path) {
            try {
                if ($disk->exists($path)) {
                    $disk->delete($path);
                }
            } catch (Throwable $e) {
                Log::warning('Gagal membuang berkas lokal siswa yang dihapus', [
                    'student_id' => $this->studentId,
                    'path' => $path,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
