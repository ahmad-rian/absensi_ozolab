<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class PhotoCropService
{
    /**
     * Process a raw photo: resize, smart crop to 3:4 portrait, save as WebP.
     *
     * @param  string  $inputPath  Full path to input image
     * @param  string  $storagePath  Relative path for storage (e.g. photos/students/1/avatar.webp)
     * @return string Storage path of the cropped photo
     */
    public function cropAndStore(string $inputPath, string $storagePath, int $quality = 85): string
    {
        $info = getimagesize($inputPath);
        if (! $info) {
            throw new \RuntimeException('Cannot read image file.');
        }

        $image = $this->loadImage($inputPath, $info[2]);
        $origW = imagesx($image);
        $origH = imagesy($image);

        // Step 1: Resize to max 800px on longest side (for performance)
        $maxDim = 800;
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

        // Step 3: Save as WebP
        $fullPath = Storage::disk('public')->path($storagePath);
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $written = imagewebp($image, $fullPath, $quality);
        imagedestroy($image);

        if (! $written || ! file_exists($fullPath)) {
            throw new \RuntimeException('Failed to write WebP image to: '.$storagePath);
        }

        return $storagePath;
    }

    /**
     * Smart crop to portrait ratio with face-aware positioning.
     * Uses skin-tone heuristic to find face area.
     */
    private function smartCropPortrait(\GdImage $image, int $w, int $h, float $targetRatio): \GdImage
    {
        $currentRatio = $w / $h;

        if (abs($currentRatio - $targetRatio) < 0.01) {
            return $image; // Already correct ratio
        }

        // Find face center using skin-tone heuristic
        $faceCenter = $this->detectFaceCenter($image, $w, $h);

        if ($currentRatio > $targetRatio) {
            // Image is wider than target — crop sides
            $cropW = (int) ($h * $targetRatio);
            $cropH = $h;

            // Center on face X position, fallback to center
            $centerX = $faceCenter ? $faceCenter['x'] : $w / 2;
            $cropX = (int) max(0, min($w - $cropW, $centerX - $cropW / 2));
            $cropY = 0;
        } else {
            // Image is taller than target — crop top/bottom
            $cropW = $w;
            $cropH = (int) ($w / $targetRatio);

            // Position face in upper third of frame (portrait convention)
            if ($faceCenter) {
                // Place face center at ~35% from top
                $targetFaceY = $cropH * 0.35;
                $cropY = (int) max(0, min($h - $cropH, $faceCenter['y'] - $targetFaceY));
            } else {
                // No face detected: crop from top with slight offset (30% bias)
                // This works well for half-body and full-body photos
                $cropY = (int) max(0, ($h - $cropH) * 0.15);
            }
            $cropX = 0;
        }

        $cropped = imagecreatetruecolor($cropW, $cropH);
        imagecopyresampled($cropped, $image, 0, 0, $cropX, $cropY, $cropW, $cropH, $cropW, $cropH);
        imagedestroy($image);

        return $cropped;
    }

    /**
     * Detect approximate face center using skin-tone sampling.
     * Scans the image in a grid and finds the densest skin-tone cluster.
     *
     * @return array{x: int, y: int}|null
     */
    private function detectFaceCenter(\GdImage $image, int $w, int $h): ?array
    {
        $stepX = max(1, (int) ($w / 40));
        $stepY = max(1, (int) ($h / 40));
        $skinPoints = [];

        // Focus on upper 70% of image (face is usually not at bottom)
        $scanH = (int) ($h * 0.7);

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

        // Need enough skin pixels to be confident
        if (count($skinPoints) < 15) {
            return null;
        }

        // Find the densest cluster of skin pixels (likely the face)
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
     *
     * @param  array<int, array{x: int, y: int}>  $points
     * @return array{x: int, y: int}
     */
    private function findDensestCluster(array $points, int $imgW, int $imgH): array
    {
        // Divide image into grid cells
        $cellSize = max($imgW, $imgH) / 8;
        $cells = [];

        foreach ($points as $p) {
            $cx = (int) ($p['x'] / $cellSize);
            $cy = (int) ($p['y'] / $cellSize);
            $key = "{$cx},{$cy}";
            if (! isset($cells[$key])) {
                $cells[$key] = ['count' => 0, 'sumX' => 0, 'sumY' => 0];
            }
            $cells[$key]['count']++;
            $cells[$key]['sumX'] += $p['x'];
            $cells[$key]['sumY'] += $p['y'];
        }

        // Find cell with most skin pixels
        $maxCell = null;
        $maxCount = 0;
        foreach ($cells as $cell) {
            if ($cell['count'] > $maxCount) {
                $maxCount = $cell['count'];
                $maxCell = $cell;
            }
        }

        if (! $maxCell) {
            return ['x' => $imgW / 2, 'y' => $imgH / 3];
        }

        return [
            'x' => (int) ($maxCell['sumX'] / $maxCell['count']),
            'y' => (int) ($maxCell['sumY'] / $maxCell['count']),
        ];
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
