<?php

namespace App\Jobs;

use App\Models\CardGenerationLog;
use App\Models\School;
use App\Models\Student;
use App\Services\GoogleDriveService;
use App\Services\PhotoSheetGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GeneratePhotoSheetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(
        public string $logId,
        public string $studentId,
        public string $template,
        public string $caption = '',
    ) {
        $this->onQueue(config('cards.queue'));
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [20, 60, 180];
    }

    public function handle(PhotoSheetGeneratorService $service): void
    {
        $log = CardGenerationLog::find($this->logId);
        if (! $log) {
            return;
        }

        $student = Student::find($this->studentId);
        if (! $student) {
            $log->update(['status' => 'failed', 'error_message' => 'Siswa tidak ditemukan.']);

            return;
        }

        try {
            $path = $service->generate($student, $this->template, $this->caption)['path'];

            $school = School::with('driveConfig')->find($student->school_id);
            $driveConfig = $school?->driveConfig;

            if ($driveConfig && $driveConfig->is_active) {
                $drive = GoogleDriveService::forSchool($driveConfig);
                $drive->ensureSubfolders();

                $folderId = $driveConfig->sheets_folder_id ?: $driveConfig->root_folder_id;
                $driveFile = $drive->uploadFile(Storage::disk('public')->path($path), basename($path), $folderId, 'image/png');
                $driveUrl = $drive->makePublic($driveFile->getId());

                $uploaded = ! empty($driveUrl);
                if ($uploaded) {
                    Storage::disk('public')->delete($path);
                }

                $log->update([
                    'status' => 'completed',
                    'file_path' => $uploaded ? null : $path,
                    'drive_file_id' => $driveFile->getId(),
                    'drive_url' => $driveUrl ?: null,
                ]);
            } else {
                $log->update(['status' => 'completed', 'file_path' => $path, 'drive_url' => null]);
            }
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error_message' => Str::limit($e->getMessage(), 500)]);
        }
    }

    public function failed(\Throwable $e): void
    {
        CardGenerationLog::where('id', $this->logId)->update([
            'status' => 'failed',
            'error_message' => Str::limit($e->getMessage(), 500),
        ]);
    }
}
