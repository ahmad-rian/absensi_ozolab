<?php

namespace App\Services;

use App\Models\CardGenerationLog;
use App\Models\School;
use App\Models\SchoolCardLayout;
use App\Models\SchoolFrame;
use App\Models\Student;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;

class CardGeneratorService
{
    /**
     * Generate a card image for a single student.
     *
     * @return array{path: string, html: string}
     */
    public function generateCard(Student $student, SchoolCardLayout $layout, ?School $school = null): array
    {
        $school = $school ?? $student->school;
        $student->loadMissing(['classroom']);

        $qrGenerator = app(Attendance\QrTokenGenerator::class);
        $qrSvg = $qrGenerator->renderSvg($student);

        $html = View::make('cards.student-card', [
            'student' => $student,
            'school' => $school,
            'layout' => $layout,
            'config' => $layout->layout_config ?? [],
            'qrSvg' => $qrSvg,
            'logoUrl' => $school->logo_path
                ? Storage::disk('public')->url($school->logo_path)
                : null,
            'photoUrl' => $student->photo_path
                ? Storage::disk('public')->url($student->photo_path)
                : null,
            'frameUrl' => $this->resolveFrameUrl($layout),
        ])->render();

        $filename = sprintf(
            'cards/%d/%s-%s.png',
            $school->id,
            Str::slug($student->full_name),
            $student->nis ?? $student->id,
        );

        $fullPath = Storage::disk('public')->path($filename);
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->renderHtmlToImage($html, $fullPath, $layout);

        return [
            'path' => $filename,
            'html' => $html,
        ];
    }

    /**
     * Generate card and log it.
     */
    public function generateAndLog(Student $student, SchoolCardLayout $layout, string $generatedBy = 'admin'): CardGenerationLog
    {
        $log = CardGenerationLog::create([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'school_card_layout_id' => $layout->id,
            'type' => 'card',
            'status' => 'processing',
            'generated_by' => $generatedBy,
        ]);

        try {
            $result = $this->generateCard($student, $layout);

            $log->update([
                'status' => 'completed',
                'file_path' => $result['path'],
            ]);

            // Upload to Drive if configured
            $this->uploadToDrive($log, $result['path']);
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 500),
            ]);
        }

        return $log->fresh();
    }

    /**
     * Render HTML to PNG using Browsershot.
     */
    private function renderHtmlToImage(string $html, string $outputPath, SchoolCardLayout $layout): void
    {
        $config = $layout->layout_config ?? [];
        $width = $config['card_width'] ?? 638;
        $height = $config['card_height'] ?? 1011;

        Browsershot::html($html)
            ->windowSize($width, $height)
            ->deviceScaleFactor(2)
            ->waitUntilNetworkIdle()
            ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox'])
            ->save($outputPath);
    }

    /**
     * Upload generated card to Google Drive if configured.
     */
    private function uploadToDrive(CardGenerationLog $log, string $filePath): void
    {
        $school = School::with('driveConfig')->find($log->school_id);
        $driveConfig = $school?->driveConfig;

        if (! $driveConfig || ! $driveConfig->is_active || ! $driveConfig->service_account_json) {
            return;
        }

        try {
            $service = GoogleDriveService::forSchool($driveConfig);
            $service->ensureSubfolders();

            $fullPath = Storage::disk('public')->path($filePath);
            $fileName = basename($filePath);

            $driveFile = $service->uploadFile($fullPath, $fileName, $driveConfig->cards_folder_id, 'image/png');
            $driveUrl = $service->makePublic($driveFile->getId());

            $log->update([
                'drive_file_id' => $driveFile->getId(),
                'drive_url' => $driveUrl,
            ]);
        } catch (\Throwable $e) {
            // Don't fail the whole generation if Drive upload fails
            Log::warning('Drive upload failed for card', [
                'log_id' => $log->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveFrameUrl(SchoolCardLayout $layout): ?string
    {
        $config = $layout->layout_config ?? [];
        $frameId = $config['frame_id'] ?? null;

        if (! $frameId) {
            return null;
        }

        $frame = SchoolFrame::where('school_id', $layout->school_id)->find($frameId);

        return $frame?->image_path
            ? Storage::disk('public')->url($frame->image_path)
            : null;
    }
}
