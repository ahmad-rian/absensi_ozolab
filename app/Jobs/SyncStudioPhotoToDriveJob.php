<?php

namespace App\Jobs;

use App\Models\CardGenerationLog;
use App\Models\School;
use App\Models\Student;
use App\Services\GoogleDriveService;
use App\Support\StudentDriveNaming;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Naikkan sepasang berkas Tyas Studio ke folder Drive siswa.
 *
 * Dua berkas, satu siswa: `{awalan}ori.jpg` (jepretan apa adanya) dan
 * `{awalan}studio.png` (hasil potong 16:21). Keduanya berkas BARU di folder
 * siswa; pas foto kartu (`{awalan}foto.png`) tidak disentuh sama sekali.
 *
 * Foto ulang siswa yang sama menimpa ISI kedua berkas itu lewat
 * `replaceStudentOutput` — id dan tautan Drive-nya bertahan, jadi tautan yang
 * sudah terlanjur dibagikan tidak mati, dan foldernya tidak menumpuk.
 *
 * Kembaran `SyncStudentPhotoToDriveJob`, termasuk semua penjaganya. Yang beda
 * cuma: dua berkas, dan `students.photo_path` tidak ikut berubah.
 */
class SyncStudioPhotoToDriveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public string $studentId,
        public string $logId,
        public string $jalurOri,
        public string $jalurCrop,
    ) {
        $this->onQueue(config('cards.queue'));
    }

    public function handle(): void
    {
        // Global scope `school` mati di konteks queue — tidak ada user login —
        // jadi penyaringan sekolahnya lewat baris siswanya sendiri.
        $student = Student::withoutGlobalScope('school')->find($this->studentId);
        $log = CardGenerationLog::withoutGlobalScope('school')->find($this->logId);

        if (! $student || ! $log) {
            $this->bersihkan();

            return;
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($this->jalurOri) || ! $disk->exists($this->jalurCrop)) {
            $this->gagal($log, 'Berkas foto sementara sudah tidak ada di server.');

            return;
        }

        $config = School::with('driveConfig')->find($student->school_id)?->driveConfig;

        if (! $config || ! $config->is_active) {
            $this->gagal($log, 'Google Drive belum aktif untuk sekolah ini.');

            return;
        }

        if (! GoogleDriveService::hasGlobalCredentials() && ! $config->service_account_json) {
            $this->gagal($log, 'Kredensial Google Drive belum diatur.');

            return;
        }

        try {
            $service = GoogleDriveService::forSchool($config);
            $folderId = $service->resolveStudentFolder($student);

            if (! $folderId) {
                $this->gagal($log, 'Folder Drive siswa tidak ditemukan dan tidak bisa dibuat.');

                return;
            }

            $awalan = StudentDriveNaming::prefix($student);

            // `knownFileId` null: nama kedua berkas deterministik, jadi langkah
            // "nama persis sama" di pickReplaceable sudah menemukannya. Kalau
            // nama siswa baru saja dibetulkan dan job penyelaras belum jalan,
            // langkah "jenis sama + namaMirip" yang menangkapnya — itulah
            // gunanya `ori` dan `studio` masuk daftar jenis yang dikenal.
            $service->replaceStudentOutput(
                $disk->path($this->jalurOri),
                $student,
                $folderId,
                $awalan.'ori.jpg',
                null,
                'image/jpeg',
            );

            $crop = $service->replaceStudentOutput(
                $disk->path($this->jalurCrop),
                $student,
                $folderId,
                $awalan.'studio.png',
                null,
                'image/png',
            );

            $student->forceFill(['drive_folder_id' => $folderId])->saveQuietly();

            $log->update([
                'status' => 'completed',
                'drive_file_id' => $crop->getId(),
                'drive_url' => $service->makePublic($crop->getId()),
            ]);
        } catch (Throwable $e) {
            Log::warning('Gagal menaikkan foto Tyas Studio ke Drive', [
                'student_id' => $this->studentId,
                'message' => $e->getMessage(),
            ]);

            $this->gagal($log, $e->getMessage());

            return;
        }

        $this->bersihkan();
    }

    /**
     * Berkas sementara dibuang, berhasil maupun gagal.
     *
     * Foto studio mentah bisa 5 MB dan datang beruntun sepanjang hari. Disk
     * penuh sudah pernah menjatuhkan server ini.
     */
    private function bersihkan(): void
    {
        Storage::disk('local')->deleteDirectory(dirname($this->jalurOri));
    }

    /**
     * Ditandai gagal, bukan dibiarkan `processing`.
     *
     * Studio menanyakan status sampai berubah; baris yang menggantung membuat
     * layarnya menunggu selamanya tanpa pernah menyebut apa yang salah.
     */
    private function gagal(CardGenerationLog $log, string $pesan): void
    {
        $log->update(['status' => 'failed', 'error_message' => $pesan]);

        $this->bersihkan();
    }
}
