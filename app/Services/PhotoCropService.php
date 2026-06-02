<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class PhotoCropService
{
    // Card photo slot ratio: 16mm wide × 21mm tall
    private const SLOT_RATIO = 16 / 21; // 0.762

    // Minimum output dimensions for quality
    private const MIN_WIDTH = 480;

    private const MIN_HEIGHT = 630;

    /**
     * Process a raw photo: fix orientation, smart crop for ID card, save as PNG.
     *
     * @param  string  $inputPath  Full path to input image
     * @param  string  $storagePath  Relative path for storage
     * @return string Storage path of the cropped photo
     */
    public function cropAndStore(string $inputPath, string $storagePath, int $quality = 9): string
    {
        $info = getimagesize($inputPath);
        if (! $info) {
            throw new \RuntimeException('Cannot read image file.');
        }

        $image = $this->loadImage($inputPath, $info[2]);

        // Step 0: Fix EXIF orientation (phone photos)
        $image = $this->fixExifOrientation($image, $inputPath, $info[2]);

        $w = imagesx($image);
        $h = imagesy($image);

        // Step 1: Resize to max 1600px (keep enough detail for face detection)
        $maxDim = 1600;
        if ($w > $maxDim || $h > $maxDim) {
            $scale = min($maxDim / $w, $maxDim / $h);
            $newW = (int) ($w * $scale);
            $newH = (int) ($h * $scale);
            $resized = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $w, $h);
            imagedestroy($image);
            $image = $resized;
            $w = $newW;
            $h = $newH;
        }

        // Step 2: Smart crop to card slot ratio (16:21) with face-centered framing
        $image = $this->smartCropForCard($image, $w, $h);

        // Step 3: Ensure minimum output resolution
        $finalW = imagesx($image);
        $finalH = imagesy($image);
        if ($finalW < self::MIN_WIDTH || $finalH < self::MIN_HEIGHT) {
            $upscale = max(self::MIN_WIDTH / $finalW, self::MIN_HEIGHT / $finalH);
            $upW = (int) ($finalW * $upscale);
            $upH = (int) ($finalH * $upscale);
            $upscaled = imagecreatetruecolor($upW, $upH);
            imagecopyresampled($upscaled, $image, 0, 0, 0, 0, $upW, $upH, $finalW, $finalH);
            imagedestroy($image);
            $image = $upscaled;
        }

        // Step 4: Save as PNG
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
     * Smart crop optimized for ID card photos.
     *
     * Strategy: detect face region, then create a tight crop that frames
     * head + upper shoulders — like a proper passport/ID photo.
     * Uses card slot ratio (16:21) so no additional cropping is needed in CSS.
     */
    private function smartCropForCard(\GdImage $image, int $w, int $h): \GdImage
    {
        $face = $this->detectFaceRegion($image, $w, $h);

        if ($face) {
            return $this->cropAroundFace($image, $w, $h, $face);
        }

        // No face detected — fall back to center-top crop
        return $this->fallbackCrop($image, $w, $h);
    }

    /**
     * Crop the image tightly around the detected face for an ID card look.
     *
     * @param  array{x: int, y: int, w: int, h: int}  $face  Bounding box of face
     */
    private function cropAroundFace(\GdImage $image, int $imgW, int $imgH, array $face): \GdImage
    {
        $faceX = $face['x'];
        $faceY = $face['y'];
        $faceW = $face['w'];
        $faceH = $face['h'];
        $faceCenterX = $faceX + $faceW / 2;
        $faceCenterY = $faceY + $faceH / 2;

        // School ID photo: headroom + face + neck + tie + chest
        // Face occupies ~25% of crop height — shows body with proper framing
        $cropH = (int) ($faceH / 0.25); // face = 25% of crop height
        $cropW = (int) ($cropH * self::SLOT_RATIO);

        // Ensure crop doesn't exceed image
        $cropW = min($cropW, $imgW);
        $cropH = min($cropH, $imgH);

        // Recalculate to maintain ratio after clamping
        if ($cropW / $cropH > self::SLOT_RATIO) {
            $cropW = (int) ($cropH * self::SLOT_RATIO);
        } else {
            $cropH = (int) ($cropW / self::SLOT_RATIO);
        }

        // Position face center at 42% from top of crop — generous headroom
        $desiredFaceCenterY = (int) ($cropH * 0.42);
        $rawCropY = (int) ($faceCenterY - $desiredFaceCenterY);
        $cropX = (int) ($faceCenterX - $cropW / 2);

        // Clamp X to image bounds
        $cropX = max(0, min($imgW - $cropW, $cropX));

        if ($rawCropY < 0) {
            // Not enough space above in original photo — pad with background color
            $shift = abs($rawCropY); // how many pixels to pad on top
            $bgColor = imagecolorat($image, 5, 5);
            $padded = imagecreatetruecolor($cropW, $cropH);
            imagefill($padded, 0, 0, $bgColor);

            // Copy from top of image (y=0), place it $shift pixels down in output
            $srcH = min($cropH - $shift, $imgH);
            imagecopyresampled($padded, $image, 0, $shift, $cropX, 0, $cropW, $srcH, $cropW, $srcH);
            imagedestroy($image);

            return $padded;
        }

        // Normal case — enough space above
        $cropY = min($rawCropY, $imgH - $cropH);
        $cropY = max(0, $cropY);

        $cropped = imagecreatetruecolor($cropW, $cropH);
        imagecopyresampled($cropped, $image, 0, 0, $cropX, $cropY, $cropW, $cropH, $cropW, $cropH);
        imagedestroy($image);

        return $cropped;
    }

    /**
     * Fallback crop when no face is detected.
     * Assumes standard portrait photo — crops center-top with card ratio.
     */
    private function fallbackCrop(\GdImage $image, int $w, int $h): \GdImage
    {
        $currentRatio = $w / $h;

        if ($currentRatio > self::SLOT_RATIO) {
            // Wider than needed — crop sides
            $cropW = (int) ($h * self::SLOT_RATIO);
            $cropH = $h;
            $cropX = (int) (($w - $cropW) / 2);
            $cropY = 0;
        } else {
            // Taller than needed — crop bottom mostly
            $cropW = $w;
            $cropH = (int) ($w / self::SLOT_RATIO);
            $cropX = 0;
            $cropY = (int) (($h - $cropH) * 0.08); // keep mostly top
        }

        $cropped = imagecreatetruecolor($cropW, $cropH);
        imagecopyresampled($cropped, $image, 0, 0, $cropX, $cropY, $cropW, $cropH, $cropW, $cropH);
        imagedestroy($image);

        return $cropped;
    }

    /**
     * Detect face region using multi-pass skin-tone analysis.
     *
     * Pass 1: Scan upper portion for skin-tone pixels on a grid
     * Pass 2: Find the densest vertical column of skin (face is vertically compact)
     * Pass 3: Extract bounding box of the face cluster, excluding outliers
     *
     * @return array{x: int, y: int, w: int, h: int}|null Bounding box or null
     */
    private function detectFaceRegion(\GdImage $image, int $w, int $h): ?array
    {
        // Higher resolution scan for better accuracy
        $stepX = max(1, (int) ($w / 80));
        $stepY = max(1, (int) ($h / 80));
        $skinPoints = [];

        // Only scan upper 60% — face is always in the top portion
        $scanH = (int) ($h * 0.60);

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

        if (count($skinPoints) < 20) {
            return null;
        }

        // Find densest cluster using smaller grid cells for precision
        $cellSize = max($w, $h) / 16;
        $cells = [];

        foreach ($skinPoints as $p) {
            $cx = (int) ($p['x'] / $cellSize);
            $cy = (int) ($p['y'] / $cellSize);
            $key = "{$cx},{$cy}";
            if (! isset($cells[$key])) {
                $cells[$key] = ['count' => 0, 'sumX' => 0, 'sumY' => 0, 'points' => []];
            }
            $cells[$key]['count']++;
            $cells[$key]['sumX'] += $p['x'];
            $cells[$key]['sumY'] += $p['y'];
            $cells[$key]['points'][] = $p;
        }

        // Find the densest cell and merge with adjacent cells
        $maxKey = null;
        $maxCount = 0;
        foreach ($cells as $key => $cell) {
            if ($cell['count'] > $maxCount) {
                $maxCount = $cell['count'];
                $maxKey = $key;
            }
        }

        if (! $maxKey) {
            return null;
        }

        // Merge the densest cell with its neighbors to get full face region
        [$bestCx, $bestCy] = explode(',', $maxKey);
        $bestCx = (int) $bestCx;
        $bestCy = (int) $bestCy;

        $facePoints = [];
        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                $nk = ($bestCx + $dx).','.($bestCy + $dy);
                if (isset($cells[$nk])) {
                    $facePoints = array_merge($facePoints, $cells[$nk]['points']);
                }
            }
        }

        if (count($facePoints) < 10) {
            return null;
        }

        // Calculate bounding box with percentile trimming (remove outliers)
        $xs = array_column($facePoints, 'x');
        $ys = array_column($facePoints, 'y');
        sort($xs);
        sort($ys);

        $trim = (int) (count($xs) * 0.10); // trim 10% from each end
        $trimmedXs = array_slice($xs, $trim, -$trim ?: null);
        $trimmedYs = array_slice($ys, $trim, -$trim ?: null);

        if (empty($trimmedXs) || empty($trimmedYs)) {
            return null;
        }

        $minX = $trimmedXs[0];
        $maxX = end($trimmedXs);
        $minY = $trimmedYs[0];
        $maxY = end($trimmedYs);

        $faceW = max($maxX - $minX, (int) ($w * 0.08));
        $faceH = max($maxY - $minY, (int) ($h * 0.08));

        // Face should be roughly as wide as tall (or taller). If width >> height, it's probably not a face.
        if ($faceW > $faceH * 2.5) {
            return null;
        }

        return [
            'x' => $minX,
            'y' => $minY,
            'w' => $faceW,
            'h' => $faceH,
        ];
    }

    /**
     * Check if a pixel color matches skin tone range.
     */
    private function isSkinTone(int $r, int $g, int $b): bool
    {
        // R > G > B pattern
        if ($r <= $g || $g <= $b) {
            return false;
        }

        if ($r < 60) {
            return false;
        }

        // Not too saturated (reject vivid clothing)
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $saturation = $max > 0 ? ($max - $min) / $max : 0;
        if ($saturation > 0.68) {
            return false;
        }

        // R-G difference within skin range
        $rgDiff = $r - $g;
        if ($rgDiff < 10 || $rgDiff > 120) {
            return false;
        }

        // Luminance check
        $luminance = 0.299 * $r + 0.587 * $g + 0.114 * $b;
        if ($luminance < 50 || $luminance > 230) {
            return false;
        }

        return true;
    }

    /**
     * Fix EXIF orientation — phone photos often have rotation metadata
     * that GD doesn't apply automatically, causing "tilted" images.
     */
    private function fixExifOrientation(\GdImage $image, string $path, int $type): \GdImage
    {
        if ($type !== IMAGETYPE_JPEG) {
            return $image;
        }

        $orientation = null;

        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $orientation = $exif['Orientation'] ?? null;
        }

        if ($orientation === null) {
            $orientation = $this->readJpegOrientation($path);
        }

        if (! $orientation) {
            return $image;
        }

        $rotated = match ((int) $orientation) {
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

    /**
     * Read JPEG EXIF orientation tag directly from file bytes.
     * Fallback for when the PHP exif extension is not installed.
     */
    private function readJpegOrientation(string $path): ?int
    {
        $data = @file_get_contents($path, false, null, 0, 65536);
        if (! $data || strlen($data) < 12) {
            return null;
        }

        if (ord($data[0]) !== 0xFF || ord($data[1]) !== 0xD8) {
            return null;
        }

        $offset = 2;
        $len = strlen($data);

        while ($offset < $len - 4) {
            if (ord($data[$offset]) !== 0xFF) {
                return null;
            }

            $marker = ord($data[$offset + 1]);

            if ($marker === 0xE1) {
                if (substr($data, $offset + 4, 6) !== "Exif\x00\x00") {
                    return null;
                }

                $tiffStart = $offset + 10;
                $byteOrder = substr($data, $tiffStart, 2);
                $littleEndian = $byteOrder === 'II';

                $read16 = function (int $pos) use ($data, $littleEndian) {
                    $a = ord($data[$pos]);
                    $b = ord($data[$pos + 1]);

                    return $littleEndian ? ($b << 8) | $a : ($a << 8) | $b;
                };

                $ifdOffset = $tiffStart + 4;
                $firstIfd = $tiffStart + $read16($ifdOffset);
                $numEntries = $read16($firstIfd);

                for ($i = 0; $i < $numEntries && $firstIfd + 2 + ($i * 12) + 12 <= $len; $i++) {
                    $entryOffset = $firstIfd + 2 + ($i * 12);
                    $tag = $read16($entryOffset);

                    if ($tag === 0x0112) {
                        return $read16($entryOffset + 8);
                    }
                }

                return null;
            }

            if ($marker === 0xDA) {
                return null;
            }

            $segLen = (ord($data[$offset + 2]) << 8) | ord($data[$offset + 3]);
            $offset += 2 + $segLen;
        }

        return null;
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
