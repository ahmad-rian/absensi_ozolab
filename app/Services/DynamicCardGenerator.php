<?php

namespace App\Services;

use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Models\SchoolFrame;
use App\Support\ChromeBinary;
use App\Support\GambarCetak;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
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
        $html = $this->renderHtml($form, $submission);

        $filename = sprintf('card-forms/%s/%s.%s', $form->id, $submission->id.'-'.Str::ulid(), GambarCetak::EKSTENSI);

        $fullPath = Storage::disk('public')->path($filename);
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        try {
            $this->renderHtmlToImage($html, $fullPath, $isPortrait);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($filename);
            throw $exception;
        }

        return ['path' => $filename, 'html' => $html];
    }

    public function renderHtml(CardForm $form, CardFormSubmission $submission): string
    {
        $config = $form->normalizedConfig();
        $exportMm = 15.748; // 400 DPI

        return View::make('cards.dynamic-card', [
            'config' => $config,
            'orientation' => $config['orientation'] ?? 'landscape',
            'values' => $submission->data ?? [],
            'photoUrl' => $this->toBase64DataUri($submission->photo_path),
            'frameUrl' => $this->resolveFrameUrl($config['frame_id'] ?? null),
            // Token absensi dicetak HURUF BESAR: QR jadi mode alfanumerik (versi 3,
            // 29×29) alih-alih mode byte (versi 4, 33×33), kotaknya lebih besar
            // di ukuran cetak yang sama. Scanner menerima kedua bentuk huruf.
            'qrSvgs' => $this->qrCodes($config, array_merge($submission->data ?? [], [CardAttendanceService::QR_SOURCE => strtoupper(app(CardAttendanceService::class)->qrToken($submission))])),
            'exportMm' => $exportMm,
        ])->render();

    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function qrCodes(array $config, array $values): array
    {
        $codes = [];
        $writer = new Writer(new ImageRenderer(new RendererStyle(300, 4), new SvgImageBackEnd));

        foreach ($config['elements'] as $id => $element) {
            if (($element['type'] ?? '') !== 'qr' || empty($element['enabled'])) {
                continue;
            }

            $value = $values[$element['source'] ?? ''] ?? null;
            if (! is_scalar($value) || trim((string) $value) === '') {
                continue;
            }

            $codes[$id] = $writer->writeString((string) $value);
        }

        return $codes;
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
            ->timeout(120)
            ->waitUntilNetworkIdle()
            ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox']);

        ChromeBinary::applyTo($browsershot);

        GambarCetak::simpan($browsershot, $outputPath);
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
