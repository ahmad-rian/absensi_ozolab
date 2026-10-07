<?php

namespace App\Services;

use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Models\School;
use App\Models\User;
use App\Services\Student\StudentDrivePhotoLocator;
use App\Support\GambarCetak;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DynamicCardFiles
{
    public function __construct(private StudentDrivePhotoLocator $locator) {}

    public function drive(CardForm $form): ?GoogleDriveService
    {
        $creator = $form->created_by ? User::find($form->created_by) : null;
        $school = $creator?->school_id ? School::with('driveConfig')->find($creator->school_id) : null;
        $config = $school?->driveConfig;
        if (! $config?->is_active || (! GoogleDriveService::hasGlobalCredentials() && ! $config->service_account_json)) {
            return null;
        }

        return $this->locator->driveFor($config);
    }

    /** @return array{drive_file_id: ?string, drive_url: ?string} */
    public function publish(CardFormSubmission $participant, string $path): array
    {
        $id = null;
        try {
            $drive = $this->drive($participant->cardForm);
            if ($drive) {
                $drive->ensureSubfolders();
                $creator = User::find($participant->cardForm->created_by);
                $config = School::with('driveConfig')->find($creator->school_id)->driveConfig;
                $file = $drive->uploadFile(Storage::disk('public')->path($path), Str::slug($participant->cardForm->name).'-'.basename($path), $config->cards_folder_id ?: $config->root_folder_id, GambarCetak::mime($path));
                $id = $file->getId();

                return ['drive_file_id' => $id, 'drive_url' => $drive->makePublic($id)];
            }
        } catch (\Throwable $exception) {
            if ($id) {
                $this->deleteDrive($participant->cardForm, $id);
            }
            Log::warning('Dynamic card Drive upload failed', ['submission_id' => $participant->id, 'error' => $exception->getMessage()]);
        }

        return ['drive_file_id' => null, 'drive_url' => null];
    }

    public function deleteDrive(CardForm $form, ?string $id): void
    {
        if (! $id) {
            return;
        }
        try {
            $this->drive($form)?->delete($id);
        } catch (\Throwable $exception) {
            Log::warning('Dynamic card Drive cleanup failed', ['file_id' => $id, 'error' => $exception->getMessage()]);
        }
    }

    public function response(CardFormSubmission $participant, bool $download): BinaryFileResponse
    {
        $temporary = false;
        if ($participant->file_path && Storage::disk('public')->exists($participant->file_path)) {
            $path = Storage::disk('public')->path($participant->file_path);
        } else {
            abort_unless($participant->drive_file_id, 404, 'Hasil kartu belum tersedia.');
            $path = tempnam(sys_get_temp_dir(), 'haji-card-');
            try {
                $drive = $this->drive($participant->cardForm);
                abort_unless($drive, 503, 'Google Drive belum dapat diakses.');
                $drive->downloadFile($participant->drive_file_id, $path);
            } catch (\Throwable $exception) {
                @unlink($path);
                report($exception);
                abort(503, 'Kartu dari Google Drive belum dapat diunduh. Coba kembali nanti.');
            }
            $temporary = true;
        }
        // Ekstensi dari kolom, bukan dari berkas: berkas sementara unduhan Drive tidak berekstensi.
        $jenis = $participant->file_path ?: 'kartu.png';
        $headers = ['Content-Type' => GambarCetak::mime($jenis), 'Cache-Control' => 'private, no-store'];
        $response = $download ? response()->download($path, 'kartu-'.$participant->id.'.'.pathinfo($jenis, PATHINFO_EXTENSION), $headers) : response()->file($path, $headers);

        return $response->deleteFileAfterSend($temporary);
    }
}
