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

        // 400 DPI: 1mm = 400/25.4 ≈ 15.748px
        $exportMm = 15.748;

        $config = $layout->normalizedConfig();

        $html = View::make('cards.student-card', [
            'student' => $student,
            'school' => $school,
            'layout' => $layout,
            'config' => $config,
            'orientation' => $config['orientation'] ?? 'landscape',
            'qrSvg' => $qrSvg,
            'logoUrl' => $this->toBase64DataUri($school->logo_path),
            'photoUrl' => $this->toBase64DataUri($student->photo_path),
            'frameUrl' => $this->resolveFrameUrl($layout),
            'exportMm' => $exportMm,
        ])->render();

        $filename = sprintf(
            'cards/%d/%s-%s-%s.png',
            $school->id,
            Str::slug($student->full_name),
            $student->nis ?? $student->id,
            $layout->type,
        );

        $fullPath = Storage::disk('public')->path($filename);
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->renderHtmlToImage($html, $fullPath, ($config['orientation'] ?? 'landscape') === 'portrait');

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
    private function renderHtmlToImage(string $html, string $outputPath, bool $isPortrait = false): void
    {
        // 400 DPI native: 85.6mm × 54mm = 1349 × 850px (swapped for portrait)
        $exportMm = 15.748;
        $longSide = (int) round(85.6 * $exportMm);
        $shortSide = (int) round(54 * $exportMm);
        $width = $isPortrait ? $shortSide : $longSide;
        $height = $isPortrait ? $longSide : $shortSide;

        $browsershot = Browsershot::html($html)
            ->windowSize($width, $height)
            ->deviceScaleFactor(1)
            ->waitUntilNetworkIdle()
            ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox']);

        $chromePath = config('services.chrome.path');
        if ($chromePath && file_exists($chromePath)) {
            $browsershot->setChromePath($chromePath);
        }

        $browsershot->save($outputPath);
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

    /**
     * Convert a storage path to a base64 data URI for inline rendering in Browsershot.
     */
    private function toBase64DataUri(?string $storagePath): ?string
    {
        if (! $storagePath) {
            return null;
        }

        $fullPath = Storage::disk('public')->path($storagePath);
        if (! file_exists($fullPath)) {
            return null;
        }

        $mime = mime_content_type($fullPath) ?: 'image/png';
        $data = base64_encode(file_get_contents($fullPath));

        return "data:{$mime};base64,{$data}";
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
            ? $this->toBase64DataUri($frame->image_path)
            : null;
    }
}
