<?php

namespace App\Jobs;

use App\Models\CardGenerationLog;
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
 * Ganti pas foto siswa di Google Drive setelah admin mengunggah yang baru.
 *
 * HANYA foto. Kartu digital dan lembar cetak sengaja tidak ikut dibuat ulang —
 * merender kartu memanggil headless Chrome, dan klien meminta itu tetap di
 * balik tombol supaya mengganti foto tidak menyeret pekerjaan berat.
 *
 * Terpisah dari `RegisterStudentCardsJob` karena blok Drive di job itu berada
 * di balik penjaga `generateCards`; melonggarkan penjaga tersebut akan
 * mengubah perilaku pendaftaran cepat `/quick-regis` yang sengaja tidak
 * menyentuh Drive.
 */
class SyncStudentPhotoToDriveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(
        public string $studentId,
        public string $logId,
    ) {
        $this->onQueue(config('cards.queue'));
    }

    public function handle(): void
    {
        // Global scope `school` mati di konteks queue — tidak ada user login —
        // jadi penyaringan sekolahnya harus lewat baris siswanya sendiri.
        $student = Student::withoutGlobalScope('school')->find($this->studentId);
        $log = CardGenerationLog::withoutGlobalScope('school')->find($this->logId);

        if (! $student || ! $log) {
            return;
        }

        if (! $student->photo_path) {
            $this->gagal($log, 'Siswa tidak punya pas foto tersimpan.');

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

            // `replaceStudentOutput` mengganti ISI berkas yang sudah ada
            // (`uploadType: media`), jadi id dan tautan Drive-nya bertahan —
            // tautan yang sudah terlanjur dibagikan tidak mati. Memakai
            // `uploadFile` di sini akan menumpuk berkas kedua di folder yang
            // sama begitu namanya tidak cocok persis.
            $driveFile = $service->replaceStudentOutput(
                Storage::disk('public')->path($student->photo_path),
                $student,
                $folderId,
                GoogleDriveService::studentPhotoFileName($student),
                $student->photo_drive_file_id,
                'image/png',
            );

            $student->forceFill([
                'photo_drive_file_id' => $driveFile->getId(),
                'drive_folder_id' => $folderId,
            ])->saveQuietly();

            $log->update([
                'status' => 'completed',
                'drive_file_id' => $driveFile->getId(),
                'drive_url' => $service->makePublic($driveFile->getId()),
                'file_path' => $student->photo_path,
            ]);
        } catch (Throwable $e) {
            Log::warning('Gagal menyinkronkan pas foto siswa ke Drive', [
                'student_id' => $this->studentId,
                'message' => $e->getMessage(),
            ]);

            $this->gagal($log, $e->getMessage());
        }
    }

    /**
     * Ditandai gagal, bukan dibiarkan `processing`.
     *
     * Halaman siswa memutar lencana selama statusnya `processing` dan berhenti
     * menyegarkan hanya kalau statusnya berubah — baris yang menggantung akan
     * membuat spinner berputar selamanya tanpa pernah memberi tahu apa yang
     * salah.
     */
    private function gagal(CardGenerationLog $log, string $pesan): void
    {
        $log->update(['status' => 'failed', 'error_message' => $pesan]);
    }
}
