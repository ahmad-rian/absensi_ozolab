<?php

namespace App\Jobs;

use App\Models\School;
use App\Models\Student;
use App\Services\GoogleDriveService;
use App\Services\Student\StudentDrivePhotoLocator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rapikan folder Drive seorang siswa setelah kelas / NIS / namanya berubah.
 *
 * Folder siswa duduk di `{Sekolah}/{Kelas}/{NIS - Nama}`, jadi ketiga nilai itu
 * ikut menentukan letaknya. Tanpa job ini, satu kali naik kelas membuat generate
 * berikutnya membangun folder BARU di kelas yang baru, sementara foto dan kartu
 * lama tertinggal di folder kelas lama — persis gejala "fotonya hilang" dan
 * "URL-nya ganti sendiri".
 *
 * Yang dipindah adalah foldernya, bukan isinya: id berkas di dalamnya tidak
 * berubah, sehingga tautan yang sudah tersimpan tetap hidup.
 */
class SyncStudentDriveFolderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public string $studentId)
    {
        $this->onQueue(config('cards.queue'));
    }

    public function handle(StudentDrivePhotoLocator $locator): void
    {
        $student = Student::with('classroom')->find($this->studentId);

        // Tanpa folder tersimpan tidak ada apa pun yang perlu dipindah: siswa ini
        // belum pernah menghasilkan berkas. Folder akan dibuat saat generate
        // pertama, dengan nama yang sudah benar.
        if (! $student || ! $student->drive_folder_id) {
            return;
        }

        $school = School::with('driveConfig')->find($student->school_id);
        $config = $school?->driveConfig;

        if (! $config || ! $config->is_active) {
            return;
        }

        if (! GoogleDriveService::hasGlobalCredentials() && ! $config->service_account_json) {
            return;
        }

        try {
            $drive = GoogleDriveService::forSchool($config);
            $schoolRootId = $drive->ensureSchoolRoot();

            if (! $schoolRootId) {
                return;
            }

            $drive->renameFile($student->drive_folder_id, GoogleDriveService::studentFolderName($student));

            $classFolderId = $drive->findOrCreateFolder(GoogleDriveService::classFolderName($student), $schoolRootId);
            $drive->moveFile($student->drive_folder_id, $classFolderId);
        } catch (Throwable $e) {
            Log::warning('Gagal merapikan folder Drive siswa', [
                'student_id' => $student->id,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        // Nama berkas pas foto ikut nama siswa, jadi hasil pencarian yang tersimpan
        // sudah tidak menggambarkan keadaan di Drive.
        $locator->forget($student);
    }
}
