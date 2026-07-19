<?php

namespace App\Jobs;

use App\Models\CardGenerationLog;
use App\Models\School;
use App\Models\SchoolCardLayout;
use App\Models\Student;
use App\Services\GoogleDriveService;
use App\Services\PhotoCropService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Orchestrates the async outputs for a registration: crops the photo, then fans
 * out parallel render jobs (2 cards + 1 pas-foto sheet) so they run concurrently.
 */
class RegisterStudentCardsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    /**
     * @param  array{sx: float, sy: float, sw: float, sh: float}|null  $manualCrop
     */
    public function __construct(
        public string $studentId,
        public ?string $photoFilename = null,
        public ?string $photoTemp = null,
        public ?array $manualCrop = null,
        public bool $generateCards = true,
    ) {
        $this->onQueue(config('cards.queue'));
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

        $this->processPhoto($student, $school);

        if (! $this->generateCards) {
            return;
        }

        $folderId = $this->resolveStudentDriveFolder($student, $school);

        // Result #3 — the cropped photo (already produced above). Log + upload.
        if ($student->photo_path) {
            $driveUrl = $folderId ? $this->uploadToFolder($student->photo_path, $folderId, $school) : null;
            CardGenerationLog::create([
                'school_id' => $school->id,
                'student_id' => $student->id,
                'type' => 'photo',
                'status' => 'completed',
                'file_path' => $student->photo_path,
                'drive_url' => $driveUrl,
                'generated_by' => 'registration',
            ]);
        }

        // Results #1 & #2 — OSIS + Perpustakaan cards (render in parallel).
        foreach ($this->resolveLayouts($school) as $layout) {
            $log = CardGenerationLog::create([
                'school_id' => $school->id,
                'student_id' => $student->id,
                'school_card_layout_id' => $layout->id,
                'type' => 'card',
                'status' => 'processing',
                'generated_by' => 'registration',
            ]);
            GenerateRegistrationCardJob::dispatch($log->id, $folderId);
        }

        // Result #4 — pas-foto sheet (4R), only when a photo exists.
        if ($student->photo_path) {
            $sheetLog = CardGenerationLog::create([
                'school_id' => $school->id,
                'student_id' => $student->id,
                'type' => 'photo_sheet',
                'status' => 'processing',
                'generated_by' => 'registration',
            ]);
            GenerateRegistrationCardJob::dispatch($sheetLog->id, $folderId);
        }
    }

    private function processPhoto(Student $student, School $school): void
    {
        // Reuse the temp file already downloaded during crop-preview (no 2nd Drive hit).
        if ($this->photoTemp && Storage::disk('public')->exists($this->photoTemp)) {
            try {
                $storagePath = sprintf('photos/students/%d/%d-%s.png', $school->id, $student->id, Str::slug($student->full_name));
                (new PhotoCropService)->cropAndStore(Storage::disk('public')->path($this->photoTemp), $storagePath, 9, $this->manualCrop);
                Storage::disk('public')->delete($this->photoTemp);
                $student->update(['photo_path' => $storagePath]);

                return;
            } catch (\Throwable $e) {
                Log::warning('Photo crop from temp failed, falling back to Drive', ['error' => $e->getMessage()]);
            }
        }

        if ($this->photoFilename) {
            $this->downloadPhotoFromDrive($student, $school, $this->photoFilename, $this->manualCrop);
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
            Log::warning('Failed to download photo from Drive', ['student_id' => $student->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @return Collection<int, SchoolCardLayout>
     */
    private function resolveLayouts(School $school)
    {
        $layouts = SchoolCardLayout::where('school_id', $school->id)
            ->where('is_active', true)
            ->whereIn('type', ['osis', 'perpustakaan'])
            ->get();

        if ($layouts->isNotEmpty()) {
            return $layouts;
        }

        $layouts = collect();
        $defaults = [
            'osis' => ['name' => 'Kartu OSIS', 'config' => ['card_width' => 813, 'card_height' => 513, 'header_gradient_start' => '#5dc4f5', 'header_gradient_end' => '#3aa8df', 'header_text_color' => '#06243a', 'watermark_text' => 'ORGANISASI SISWA INTRA SEKOLAH', 'show_emblem' => true, 'show_validity' => true, 'validity_text' => 'BERLAKU S/D TAMAT BELAJAR', 'show_qr' => true, 'show_signature' => true]],
            'perpustakaan' => ['name' => 'Kartu Perpustakaan', 'config' => ['card_width' => 813, 'card_height' => 513, 'header_gradient_start' => '#c9986a', 'header_gradient_end' => '#b07b4a', 'header_text_color' => '#1a1208', 'watermark_text' => 'PERPUSTAKAAN WIDYA SASTRA', 'show_emblem' => false, 'show_validity' => false, 'show_qr' => true, 'show_signature' => true]],
        ];
        foreach ($defaults as $type => $def) {
            $layouts->push(SchoolCardLayout::create(['school_id' => $school->id, 'name' => $def['name'], 'type' => $type, 'layout_config' => $def['config'], 'is_default' => true]));
        }

        return $layouts;
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
            $classFolderId = $service->findOrCreateFolder($student->classroom?->name ?? 'Tanpa Kelas', $driveConfig->cards_folder_id);
            $studentFolderName = sprintf('%s - %s', $student->nis ?? $student->id, $student->full_name);

            return $service->findOrCreateFolder($studentFolderName, $classFolderId);
        } catch (\Throwable $e) {
            Log::warning('Failed to create student Drive folder', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function uploadToFolder(string $storagePath, string $folderId, School $school): ?string
    {
        try {
            $service = GoogleDriveService::forSchool($school->driveConfig);
            $driveFile = $service->uploadFile(Storage::disk('public')->path($storagePath), basename($storagePath), $folderId, 'image/png');

            return $service->makePublic($driveFile->getId());
        } catch (\Throwable $e) {
            Log::warning('Photo Drive upload failed', ['student_id' => $school->id, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
