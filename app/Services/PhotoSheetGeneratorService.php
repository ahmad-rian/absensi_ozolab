<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;

class PhotoSheetGeneratorService
{
    /**
     * Template definitions. Dimensions in millimeters.
     * 400 DPI: 1mm = 400/25.4 ≈ 15.748px
     *
     * @var array<string, array{label: string, sheet: array{0: float, 1: float}, cols: int, rows: int, slot: array{0: float, 1: float}, gap: float}>
     */
    public const TEMPLATES = [
        '4r_3x4' => [
            'label' => '4R — 8× (3×4)',
            'sheet' => [152, 102],
            'cols' => 4,
            'rows' => 2,
            'slot' => [30, 40],
            'gap' => 3,
        ],
        '4r_2x3' => [
            'label' => '4R — 18× (2×3)',
            'sheet' => [152, 102],
            'cols' => 6,
            'rows' => 3,
            'slot' => [20, 30],
            'gap' => 2,
        ],
        '4r_4x6' => [
            'label' => '4R — 4× (4×6)',
            'sheet' => [102, 152],
            'cols' => 2,
            'rows' => 2,
            'slot' => [40, 60],
            'gap' => 3,
        ],
    ];

    /**
     * Generate a photo sheet PNG for a student.
     *
     * @return array{path: string}
     */
    public function generate(Student $student, string $template, string $caption = ''): array
    {
        $config = self::TEMPLATES[$template] ?? self::TEMPLATES['4r_3x4'];

        // 400 DPI: 1mm = 400/25.4 ≈ 15.748px
        $exportMm = 15.748;

        $count = $config['cols'] * $config['rows'];

        $html = View::make('cards.photo-sheet', [
            'exportMm' => $exportMm,
            'sheetW' => $config['sheet'][0],
            'sheetH' => $config['sheet'][1],
            'cols' => $config['cols'],
            'rows' => $config['rows'],
            'slotW' => $config['slot'][0],
            'slotH' => $config['slot'][1],
            'gap' => $config['gap'],
            'count' => $count,
            'caption' => $caption,
            'photoUrl' => $this->toBase64DataUri($student->photo_path),
        ])->render();

        $filename = sprintf(
            'sheets/%d/%s-%s-%s.png',
            $student->school_id,
            Str::slug($student->full_name),
            $student->nis ?? $student->id,
            $template,
        );

        $fullPath = Storage::disk('public')->path($filename);
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $width = (int) round($config['sheet'][0] * $exportMm);
        $height = (int) round($config['sheet'][1] * $exportMm);

        $this->renderPage($html, $fullPath, $width, $height);

        return [
            'path' => $filename,
        ];
    }

    private function renderPage(string $html, string $outputPath, int $width, int $height): void
    {
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
}
