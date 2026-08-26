<?php

namespace App\Jobs;

use App\Models\CardGenerationLog;
use App\Models\School;
use App\Services\CardGeneratorService;
use App\Services\GoogleDriveService;
use App\Services\PhotoSheetGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Renders ONE registration output (a card OR the pas-foto sheet) and uploads it
 * to the student's Drive folder. Dispatched once per output so they run in parallel.
 */
class GenerateRegistrationCardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public string $logId, public ?string $folderId = null)
    {
        $this->onQueue(config('cards.queue'));
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [20, 60, 180];
    }

    public function handle(): void
    {
        $log = CardGenerationLog::with(['student.school.driveConfig', 'cardLayout'])->find($this->logId);
        if (! $log || ! $log->student) {
            return;
        }

        $student = $log->student;
        $school = $student->school;

        try {
            if ($log->type === 'photo_sheet') {
                $path = app(PhotoSheetGeneratorService::class)->generate($student, '4r_3x4', '')['path'];
            } else {
                if (! $log->cardLayout) {
                    $log->update(['status' => 'failed', 'error_message' => 'Layout tidak ditemukan.']);

                    return;
                }
                $path = app(CardGeneratorService::class)->generateCard($student, $log->cardLayout)['path'];
            }

            $unggah = $this->folderId && $school
                ? $this->uploadToFolder($path, $this->folderId, $school, $log)
                : null;

            $log->update([
                'status' => 'completed',
                'file_path' => $path,
                // `drive_file_id` dulu tidak pernah ditulis di sini — hanya
                // URL-nya. Akibatnya justru jalur tombol "Generate Ulang Kartu",
                // yang paling sering dipakai, tidak punya pegangan id sama
                // sekali dan selalu jatuh ke pencocokan nama.
                'drive_file_id' => $unggah['id'] ?? null,
                'drive_url' => $unggah['url'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error_message' => Str::limit($e->getMessage(), 500)]);
        }
    }

    public function failed(\Throwable $e): void
    {
        CardGenerationLog::where('id', $this->logId)->update(['status' => 'failed', 'error_message' => Str::limit($e->getMessage(), 500)]);
    }

    /**
     * @return array{id: string, url: ?string}|null
     */
    private function uploadToFolder(string $storagePath, string $folderId, School $school, CardGenerationLog $log): ?array
    {
        try {
            $service = GoogleDriveService::forSchool($school->driveConfig);

            $driveFile = $service->replaceStudentOutput(
                Storage::disk('public')->path($storagePath),
                $log->student,
                $folderId,
                basename($storagePath),
                $this->lastDriveFileId($log),
                'image/png',
            );

            return ['id' => $driveFile->getId(), 'url' => $service->makePublic($driveFile->getId())];
        } catch (\Throwable $e) {
            Log::warning('Registration card Drive upload failed', ['log_id' => $this->logId, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Id berkas Drive dari generate sebelumnya untuk keluaran yang sama.
     *
     * Riwayat generate sengaja menumpuk sebagai riwayat, jadi yang dicari adalah
     * baris `completed` TERBARU untuk kombinasi siswa + jenis + layout yang sama.
     */
    private function lastDriveFileId(CardGenerationLog $log): ?string
    {
        return CardGenerationLog::withoutGlobalScope('school')
            ->where('student_id', $log->student_id)
            ->where('type', $log->type)
            ->where('school_card_layout_id', $log->school_card_layout_id)
            ->where('id', '!=', $log->id)
            ->whereNotNull('drive_file_id')
            ->latest()
            ->value('drive_file_id');
    }
}
