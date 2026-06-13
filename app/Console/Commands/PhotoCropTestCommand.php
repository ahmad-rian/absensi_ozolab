<?php

namespace App\Console\Commands;

use App\Services\PhotoCropService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('photo:crop-test
    {dir=tests/fixtures/photos/cases : Directory of source images}
    {--ref=tests/fixtures/photos/reference.jpg : Reference image cropped first}
    {--out=crop-test : Storage (public disk) output subdir}')]
#[Description('Batch-run PhotoCropService over a folder and build a contact-sheet montage for visual QA.')]
class PhotoCropTestCommand extends Command
{
    public function handle(PhotoCropService $cropper): int
    {
        @ini_set('memory_limit', '512M'); // large studio JPEGs decode big before downscale

        $dir = base_path($this->argument('dir'));
        $out = (string) $this->option('out');

        $files = [];
        $ref = $this->option('ref') ? base_path((string) $this->option('ref')) : null;
        if ($ref && is_file($ref)) {
            $files[] = $ref;
        }
        foreach (glob($dir.'/*.{jpg,jpeg,png,JPG,JPEG,PNG}', GLOB_BRACE) ?: [] as $f) {
            $files[] = $f;
        }

        if (! $files) {
            $this->error("No images found in {$dir}");

            return self::FAILURE;
        }

        $crops = [];
        $metrics = [];
        foreach ($files as $i => $src) {
            $name = sprintf('%s/%02d_%s.png', $out, $i, pathinfo($src, PATHINFO_FILENAME));
            try {
                $m = $cropper->analyze($src);
                $metrics[$src] = $m;
                $cropper->cropAndStore($src, $name);
                $crops[] = Storage::disk('public')->path($name);
                $tag = $m ? sprintf('ok=%d conf=%.2f [%s]', $m['ok'], $m['confidence'], $m['reason']) : 'no-detect';
                $this->line('✓ '.basename($src).'  '.$tag);
            } catch (\Throwable $e) {
                $this->error('✗ '.basename($src).' — '.$e->getMessage());
            }
        }

        $montagePath = $this->buildMontage($crops, $out);
        $overlayPath = $this->buildOverlay($files, $metrics, $out);

        $jsonPath = Storage::disk('public')->path($out.'/_metrics.json');
        $json = [];
        foreach ($metrics as $src => $m) {
            $json[basename($src)] = $m;
        }
        file_put_contents($jsonPath, json_encode($json, JSON_PRETTY_PRINT));

        $this->info('Crops:   '.Storage::disk('public')->path($out));
        $this->info('Montage: '.$montagePath);
        $this->info('Overlay: '.$overlayPath);
        $this->info('Metrics: '.$jsonPath);

        return self::SUCCESS;
    }

    /**
     * Draw detection anchors (head-top, eye-line, center) over each source image.
     *
     * @param  list<string>  $files
     * @param  array<string, array{ok: bool, confidence: float, reason: string, centerXPct: float, headTopPct: float, headHeightPct: float, eyeYPct: float}|null>  $metrics
     */
    private function buildOverlay(array $files, array $metrics, string $out): string
    {
        $cellW = 240;
        $cellH = 160;
        $pad = 8;
        $cols = 4;
        $rows = (int) ceil(count($files) / $cols);

        $mW = $cols * $cellW + ($cols + 1) * $pad;
        $mH = $rows * $cellH + ($rows + 1) * $pad;
        $montage = imagecreatetruecolor($mW, $mH);
        imagefill($montage, 0, 0, imagecolorallocate($montage, 30, 30, 30));

        $green = imagecolorallocate($montage, 0, 230, 0);
        $red = imagecolorallocate($montage, 255, 40, 40);
        $blue = imagecolorallocate($montage, 60, 140, 255);
        $white = imagecolorallocate($montage, 255, 255, 255);

        foreach ($files as $i => $src) {
            $info = @getimagesize($src);
            if (! $info) {
                continue;
            }
            $img = imagecreatefromstring((string) file_get_contents($src));
            if (! $img) {
                continue;
            }
            $col = $i % $cols;
            $row = (int) ($i / $cols);
            $dx = $pad + $col * ($cellW + $pad);
            $dy = $pad + $row * ($cellH + $pad);
            imagecopyresampled($montage, $img, $dx, $dy, 0, 0, $cellW, $cellH, imagesx($img), imagesy($img));
            imagedestroy($img);

            $m = $metrics[$src] ?? null;
            if ($m) {
                $yTop = $dy + (int) ($cellH * $m['headTopPct'] / 100);
                $yEye = $dy + (int) ($cellH * $m['eyeYPct'] / 100);
                $xC = $dx + (int) ($cellW * $m['centerXPct'] / 100);
                imageline($montage, $dx, $yTop, $dx + $cellW, $yTop, $green);
                imageline($montage, $dx, $yEye, $dx + $cellW, $yEye, $red);
                imageline($montage, $xC, $dy, $xC, $dy + $cellH, $blue);
                imagestring($montage, 2, $dx + 3, $dy + 2, sprintf('%s c%.2f', $m['ok'] ? 'OK' : 'FB', $m['confidence']), $white);
            } else {
                imagestring($montage, 2, $dx + 3, $dy + 2, 'NO-DETECT', $white);
            }
        }

        $path = Storage::disk('public')->path($out.'/_overlay.png');
        imagepng($montage, $path);
        imagedestroy($montage);

        return $path;
    }

    /**
     * Assemble cropped PNGs into a single grid image for one-glance QA.
     *
     * @param  list<string>  $crops
     */
    private function buildMontage(array $crops, string $out): string
    {
        $cellW = 200;
        $cellH = 262; // ≈ 16:21
        $pad = 8;
        $cols = 6;
        $rows = (int) ceil(count($crops) / $cols);

        $mW = $cols * $cellW + ($cols + 1) * $pad;
        $mH = $rows * $cellH + ($rows + 1) * $pad;
        $montage = imagecreatetruecolor($mW, $mH);
        imagefill($montage, 0, 0, imagecolorallocate($montage, 240, 240, 240));

        foreach ($crops as $i => $path) {
            if (! is_file($path)) {
                continue;
            }
            $img = imagecreatefrompng($path);
            $col = $i % $cols;
            $row = (int) ($i / $cols);
            $dx = $pad + $col * ($cellW + $pad);
            $dy = $pad + $row * ($cellH + $pad);
            imagecopyresampled($montage, $img, $dx, $dy, 0, 0, $cellW, $cellH, imagesx($img), imagesy($img));
            imagedestroy($img);
        }

        $montagePath = Storage::disk('public')->path($out.'/_montage.png');
        imagepng($montage, $montagePath);
        imagedestroy($montage);

        return $montagePath;
    }
}
