<?php

namespace App\Services;

use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Models\SchoolFrame;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;

class DynamicCardGenerator
{
    /**
     * Render a card for a dynamic-form submission using the form's layout + field values.
     *
     * @return array{path: string, html: string}
     */
    public function generate(CardForm $form, CardFormSubmission $submission): array
    {
        $config = $form->normalizedConfig();
        $isPortrait = ($config['orientation'] ?? 'landscape') === 'portrait';
        $exportMm = 15.748; // 400 DPI

        $html = View::make('cards.dynamic-card', [
            'config' => $config,
            'orientation' => $config['orientation'] ?? 'landscape',
            'values' => $submission->data ?? [],
            'photoUrl' => $this->toBase64DataUri($submission->photo_path),
            'frameUrl' => $this->resolveFrameUrl($config['frame_id'] ?? null),
            'qrSvg' => null,
            'exportMm' => $exportMm,
        ])->render();

        $filename = sprintf('card-forms/%s/%s.png', $form->id, $submission->id ?: Str::random(8));

        $fullPath = Storage::disk('public')->path($filename);
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->renderHtmlToImage($html, $fullPath, $isPortrait);

        return ['path' => $filename, 'html' => $html];
    }

    private function renderHtmlToImage(string $html, string $outputPath, bool $isPortrait): void
    {
        $exportMm = 15.748;
        $long = (int) round(85.6 * $exportMm);
        $short = (int) round(54 * $exportMm);
        $width = $isPortrait ? $short : $long;
        $height = $isPortrait ? $long : $short;

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

    private function resolveFrameUrl(?string $frameId): ?string
    {
        if (! $frameId) {
            return null;
        }
        $frame = SchoolFrame::find($frameId);

        return $frame?->image_path ? $this->toBase64DataUri($frame->image_path) : null;
    }
}
