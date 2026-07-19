<?php

namespace App\Jobs;

use App\Models\School;
use App\Models\SchoolCardLayout;
use App\Models\Student;
use App\Services\CardGeneratorService;
use App\Services\GoogleDriveService;
use App\Services\PhotoCropService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RegisterStudentCardsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    /**
     * @param  array{sx: float, sy: float, sw: float, sh: float}|null  $manualCrop
     */
    public function __construct(
        public string $studentId,
        public ?string $photoFilename = null,
        public ?array $manualCrop = null,
        public bool $generateCards = true,
    ) {
        $this->onQueue(config('cards.queue'));
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(): void
    {
        $student = Student::find($this->studentId);
        if (! $student) {
            return;
        }

        $school = School::with('driveConfig')->find($student->school_id);
        if (! $school) {
            return;
        }

        if ($this->photoFilename) {
            $this->downloadPhotoFromDrive($student, $school, $this->photoFilename, $this->manualCrop);
        }

        if ($this->generateCards) {
            $this->generateStudentCards($student, $school);
        }
    }

    /**
     * @param  array{sx: float, sy: float, sw: float, sh: float}|null  $manualCrop
     */
    private function downloadPhotoFromDrive(Student $student, School $school, string $filename, ?array $manualCrop = null): bool
    {
        $driveConfig = $school->driveConfig;
        if (! $driveConfig || ! $driveConfig->is_active) {
            return false;
        }
        if (! GoogleDriveService::hasGlobalCredentials() && ! $driveConfig->service_account_json) {
            return false;
        }

        try {
            $service = GoogleDriveService::forSchool($driveConfig);
            $searchFolderId = $driveConfig->parents_folder_id ?: $driveConfig->root_folder_id ?: 'root';
            $files = $service->findFileByName($filename, $searchFolderId);

            if (empty($files)) {
                Log::info('Photo not found in Drive', ['filename' => $filename, 'school_id' => $school->id]);

                return false;
            }

            $tempPath = tempnam(sys_get_temp_dir(), 'student_photo_');
            $service->downloadFile($files[0]['id'], $tempPath);

            $storagePath = sprintf('photos/students/%d/%d-%s.png', $school->id, $student->id, Str::slug($student->full_name));
            (new PhotoCropService)->cropAndStore($tempPath, $storagePath, 9, $manualCrop);

            @unlink($tempPath);
            $student->update(['photo_path' => $storagePath]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed to download photo from Drive', ['student_id' => $student->id, 'filename' => $filename, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function generateStudentCards(Student $student, School $school): void
    {
        $service = new CardGeneratorService;

        $layouts = SchoolCardLayout::where('school_id', $school->id)
            ->where('is_active', true)
            ->whereIn('type', ['osis', 'perpustakaan'])
            ->get();

        if ($layouts->isEmpty()) {
            $layouts = collect();
            $defaults = [
                'osis' => ['name' => 'Kartu OSIS', 'config' => ['card_width' => 813, 'card_height' => 513, 'header_gradient_start' => '#5dc4f5', 'header_gradient_end' => '#3aa8df', 'header_text_color' => '#06243a', 'watermark_text' => 'ORGANISASI SISWA INTRA SEKOLAH', 'show_emblem' => true, 'show_validity' => true, 'validity_text' => 'BERLAKU S/D TAMAT BELAJAR', 'show_qr' => true, 'show_signature' => true]],
                'perpustakaan' => ['name' => 'Kartu Perpustakaan', 'config' => ['card_width' => 813, 'card_height' => 513, 'header_gradient_start' => '#c9986a', 'header_gradient_end' => '#b07b4a', 'header_text_color' => '#1a1208', 'watermark_text' => 'PERPUSTAKAAN WIDYA SASTRA', 'show_emblem' => false, 'show_validity' => false, 'show_qr' => true, 'show_signature' => true]],
            ];
            foreach ($defaults as $type => $def) {
                $layouts->push(SchoolCardLayout::create([
                    'school_id' => $school->id,
                    'name' => $def['name'],
                    'type' => $type,
                    'layout_config' => $def['config'],
                    'is_default' => true,
                ]));
            }
        }

        $studentFolderId = $this->resolveStudentDriveFolder($student, $school);

        if ($student->photo_path && $studentFolderId) {
            $this->uploadPhotoToDrive($student, $school, $studentFolderId);
        }

        foreach ($layouts as $layout) {
            try {
                $log = $service->generateAndLog($student, $layout, 'registration');

                if ($studentFolderId && $log->file_path) {
                    $driveUrl = $this->uploadFileToDriveFolder($log->file_path, $studentFolderId, $school, 'image/png');
                    if ($driveUrl) {
                        $log->update(['drive_url' => $driveUrl]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Card generation failed during registration', ['student_id' => $student->id, 'layout_type' => $layout->type, 'error' => $e->getMessage()]);
            }
        }
    }

    private function resolveStudentDriveFolder(Student $student, School $school): ?string
    {
        $driveConfig = $school->driveConfig;
        if (! $driveConfig || ! $driveConfig->is_active || ! $driveConfig->cards_folder_id) {
            return null;
        }
        if (! GoogleDriveService::hasGlobalCredentials() && ! $driveConfig->service_account_json) {
            return null;
        }

        try {
            $service = GoogleDriveService::forSchool($driveConfig);
            $student->loadMissing('classroom');
            $classroomName = $student->classroom?->name ?? 'Tanpa Kelas';
            $classFolderId = $service->findOrCreateFolder($classroomName, $driveConfig->cards_folder_id);
            $studentFolderName = sprintf('%s - %s', $student->nis ?? $student->id, $student->full_name);

            return $service->findOrCreateFolder($studentFolderName, $classFolderId);
        } catch (\Throwable $e) {
            Log::warning('Failed to create student Drive folder', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function uploadPhotoToDrive(Student $student, School $school, string $folderId): void
    {
        try {
            $service = GoogleDriveService::forSchool($school->driveConfig);
            $fullPath = Storage::disk('public')->path($student->photo_path);
            $fileName = sprintf('foto-%s.png', Str::slug($student->full_name));
            $driveFile = $service->uploadFile($fullPath, $fileName, $folderId, 'image/png');
            $service->makePublic($driveFile->getId());
        } catch (\Throwable $e) {
            Log::warning('Photo Drive upload failed', ['student_id' => $student->id, 'error' => $e->getMessage()]);
        }
    }

    private function uploadFileToDriveFolder(string $storagePath, string $folderId, School $school, string $mimeType): ?string
    {
        try {
            $service = GoogleDriveService::forSchool($school->driveConfig);
            $fullPath = Storage::disk('public')->path($storagePath);
            $driveFile = $service->uploadFile($fullPath, basename($storagePath), $folderId, $mimeType);

            return $service->makePublic($driveFile->getId());
        } catch (\Throwable $e) {
            Log::warning('File Drive upload failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
