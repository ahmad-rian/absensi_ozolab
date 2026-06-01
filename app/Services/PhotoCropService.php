<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class PhotoCropService
{
    /**
     * Process a raw photo: resize, smart crop to 3:4 portrait, save as PNG.
     *
     * @param  string  $inputPath  Full path to input image
     * @param  string  $storagePath  Relative path for storage (e.g. photos/students/1/avatar.png)
     * @return string Storage path of the cropped photo
     */
    public function cropAndStore(string $inputPath, string $storagePath, int $quality = 9): string
    {
        $info = getimagesize($inputPath);
        if (! $info) {
            throw new \RuntimeException('Cannot read image file.');
        }

        $image = $this->loadImage($inputPath, $info[2]);

        // Step 0: Fix EXIF orientation (phone photos are often rotated)
        $image = $this->fixExifOrientation($image, $inputPath, $info[2]);

        $origW = imagesx($image);
        $origH = imagesy($image);

        // Step 1: Resize to max 1200px on longest side (HD quality)
        $maxDim = 1200;
        if ($origW > $maxDim || $origH > $maxDim) {
            $ratio = min($maxDim / $origW, $maxDim / $origH);
            $newW = (int) ($origW * $ratio);
            $newH = (int) ($origH * $ratio);
            $resized = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
            imagedestroy($image);
            $image = $resized;
            $origW = $newW;
            $origH = $newH;
        }

        // Step 2: Smart crop to 3:4 portrait ratio
        $targetRatio = 3 / 4; // width / height
        $image = $this->smartCropPortrait($image, $origW, $origH, $targetRatio);

        // Step 3: Save as PNG
        $fullPath = Storage::disk('public')->path($storagePath);
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $written = imagepng($image, $fullPath, $quality);
        imagedestroy($image);

        if (! $written || ! file_exists($fullPath)) {
            throw new \RuntimeException('Failed to write PNG image to: '.$storagePath);
        }

        return $storagePath;
    }

    /**
     * Simple smart crop to portrait ratio.
     * Centers on face if detected, otherwise crops from top with slight offset.
     */
    private function smartCropPortrait(\GdImage $image, int $w, int $h, float $targetRatio): \GdImage
    {
        $currentRatio = $w / $h;

        if (abs($currentRatio - $targetRatio) < 0.01) {
            return $image;
        }

        $faceCenter = $this->detectFaceCenter($image, $w, $h);

        if ($currentRatio > $targetRatio) {
            // Image is wider — crop sides, center on face X
            $cropW = (int) ($h * $targetRatio);
            $cropH = $h;
            $centerX = $faceCenter ? $faceCenter['x'] : (int) ($w / 2);
            $cropX = (int) max(0, min($w - $cropW, $centerX - $cropW / 2));
            $cropY = 0;
        } else {
            // Image is taller — crop top/bottom
            $cropW = $w;
            $cropH = (int) ($w / $targetRatio);

            if ($faceCenter) {
                // Place face center at ~30% from top of crop
                $cropY = (int) ($faceCenter['y'] - $cropH * 0.30);
            } else {
                // No face: start from near top (10% offset)
                $cropY = (int) (($h - $cropH) * 0.10);
            }

            $cropX = 0;
            $cropY = (int) max(0, min($h - $cropH, $cropY));
        }

        $cropped = imagecreatetruecolor($cropW, $cropH);
        imagecopyresampled($cropped, $image, 0, 0, $cropX, $cropY, $cropW, $cropH, $cropW, $cropH);
        imagedestroy($image);

        return $cropped;
    }

    /**
     * Detect approximate face center and size using skin-tone sampling.
     * Scans the image in a grid and finds the densest skin-tone cluster.
     *
     * @return array{x: int, y: int, h: int}|null
     */
    private function detectFaceCenter(\GdImage $image, int $w, int $h): ?array
    {
        $stepX = max(1, (int) ($w / 50));
        $stepY = max(1, (int) ($h / 50));
        $skinPoints = [];

        // Focus on upper 75% of image (face is usually not at bottom)
        $scanH = (int) ($h * 0.75);

        for ($y = 0; $y < $scanH; $y += $stepY) {
            for ($x = 0; $x < $w; $x += $stepX) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                if ($this->isSkinTone($r, $g, $b)) {
                    $skinPoints[] = ['x' => $x, 'y' => $y];
                }
            }
        }

        if (count($skinPoints) < 15) {
            return null;
        }

        return $this->findDensestCluster($skinPoints, $w, $h);
    }

    /**
     * Check if a pixel color matches skin tone range.
     * Covers diverse skin tones using RGB rules.
     */
    private function isSkinTone(int $r, int $g, int $b): bool
    {
        // Rule 1: R > G > B pattern (common across skin tones)
        if ($r <= $g || $g <= $b) {
            return false;
        }

        // Rule 2: Minimum brightness (not too dark)
        if ($r < 60) {
            return false;
        }

        // Rule 3: Not too saturated (not vivid red/orange clothing)
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $saturation = $max > 0 ? ($max - $min) / $max : 0;
        if ($saturation > 0.68) {
            return false;
        }

        // Rule 4: R-G difference within skin range
        $rgDiff = $r - $g;
        if ($rgDiff < 10 || $rgDiff > 120) {
            return false;
        }

        // Rule 5: Luminance check
        $luminance = 0.299 * $r + 0.587 * $g + 0.114 * $b;
        if ($luminance < 50 || $luminance > 230) {
            return false;
        }

        return true;
    }

    /**
     * Find the densest cluster of points using grid-based density estimation.
     * Returns center position and estimated face height.
     *
     * @param  array<int, array{x: int, y: int}>  $points
     * @return array{x: int, y: int, h: int}
     */
    private function findDensestCluster(array $points, int $imgW, int $imgH): array
    {
        $cellSize = max($imgW, $imgH) / 10;
        $cells = [];

        foreach ($points as $p) {
            $cx = (int) ($p['x'] / $cellSize);
            $cy = (int) ($p['y'] / $cellSize);
            $key = "{$cx},{$cy}";
            if (! isset($cells[$key])) {
                $cells[$key] = ['count' => 0, 'sumX' => 0, 'sumY' => 0, 'minY' => PHP_INT_MAX, 'maxY' => 0];
            }
            $cells[$key]['count']++;
            $cells[$key]['sumX'] += $p['x'];
            $cells[$key]['sumY'] += $p['y'];
            $cells[$key]['minY'] = min($cells[$key]['minY'], $p['y']);
            $cells[$key]['maxY'] = max($cells[$key]['maxY'], $p['y']);
        }

        // Find cell with most skin pixels and include adjacent cells
        $maxKey = null;
        $maxCount = 0;
        foreach ($cells as $key => $cell) {
            if ($cell['count'] > $maxCount) {
                $maxCount = $cell['count'];
                $maxKey = $key;
            }
        }

        if (! $maxKey || ! isset($cells[$maxKey])) {
            return ['x' => (int) ($imgW / 2), 'y' => (int) ($imgH / 3), 'h' => (int) ($imgH * 0.2)];
        }

        $cell = $cells[$maxKey];
        $centerX = (int) ($cell['sumX'] / $cell['count']);
        $centerY = (int) ($cell['sumY'] / $cell['count']);
        $faceH = max((int) ($cell['maxY'] - $cell['minY']), (int) ($imgH * 0.15));

        return ['x' => $centerX, 'y' => $centerY, 'h' => $faceH];
    }

    /**
     * Fix EXIF orientation — phone photos often have rotation metadata
     * that GD doesn't apply automatically, causing "tilted" images.
     */
    private function fixExifOrientation(\GdImage $image, string $path, int $type): \GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        if (! $exif || empty($exif['Orientation'])) {
            return $image;
        }

        $rotated = match ((int) $exif['Orientation']) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        if ($rotated) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }

    private function loadImage(string $path, int $type): \GdImage
    {
        return match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_PNG => imagecreatefrompng($path),
            IMAGETYPE_WEBP => imagecreatefromwebp($path),
            IMAGETYPE_GIF => imagecreatefromgif($path),
            default => throw new \RuntimeException('Unsupported image format: '.$type),
        };
    }
}
