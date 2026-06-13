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

    // ---- Framing constants (calibrated to reference photo) ----
    // Detected head height occupies this fraction of the final crop height.
    // Smaller = more zoomed out (more chest shown).
    private const HEAD_FRACTION = 0.34;

    // Headroom above the top of the head, as a fraction of the crop height.
    private const HEADROOM_FRACTION = 0.10;

    // ---- Background segmentation constants ----
    // Longest side of the downscaled working image used for mask analysis.
    private const WORK_MAX = 520;

    // Chroma distance (in YCbCr Cb/Cr plane) beyond which a pixel is foreground.
    private const FG_CHROMA_THRESHOLD = 18.0;

    /**
     * Process a raw photo: fix orientation, smart crop for ID card, save as PNG.
     *
     * @param  string  $inputPath  Full path to input image
     * @param  string  $storagePath  Relative path for storage
     * @return string Storage path of the cropped photo
     */
    public function cropAndStore(string $inputPath, string $storagePath, int $quality = 9): string
    {
        // Large studio JPEGs (e.g. 6000px+) decode to ~100MB of truecolor before
        // we downscale — raise the limit so cropping never OOMs mid-request.
        $this->ensureMemoryLimit(512);

        $info = getimagesize($inputPath);
        if (! $info) {
            throw new \RuntimeException('Cannot read image file.');
        }

        $image = $this->loadImage($inputPath, $info[2]);

        // Step 0: Fix EXIF orientation (phone photos)
        $image = $this->fixExifOrientation($image, $inputPath, $info[2]);

        $w = imagesx($image);
        $h = imagesy($image);

        // Step 1: Resize to max 1600px (keep enough detail for analysis)
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

        // Step 2: Smart crop to card slot ratio (16:21) with subject-centered framing
        $image = $this->smartCropForCard($image, $w, $h);

        // Step 3: Ensure minimum output resolution
        $finalW = imagesx($image);
        $finalH = imagesy($image);
        if ($finalW < self::MIN_WIDTH || $finalH < self::MIN_HEIGHT) {
            $upscale = max(self::MIN_WIDTH / $finalW, self::MIN_HEIGHT / $finalH);
            // ceil so rounding never leaves a dimension 1px under the minimum.
            $upW = (int) ceil($finalW * $upscale);
            $upH = (int) ceil($finalH * $upscale);
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
     * Strategy: segment the subject from the uniform studio backdrop, derive
     * head-top / body-center / head-size from the silhouette, then frame a
     * consistent head+shoulders+chest crop at the card slot ratio (16:21).
     */
    private function smartCropForCard(\GdImage $image, int $w, int $h): \GdImage
    {
        $subject = $this->detectSubject($image, $w, $h);

        // Only trust segmentation when it is plausible; otherwise fall back to a
        // safe center-top crop instead of emitting a garbage (e.g. empty) frame.
        if ($subject && $subject['ok']) {
            return $this->cropAroundHead($image, $w, $h, $subject);
        }

        return $this->fallbackCrop($image, $w, $h);
    }

    /**
     * Inspect detection metrics for a stored image without cropping (QA / tests).
     * Percentages are relative to the (EXIF-fixed, 1600-capped) working image.
     *
     * @return array{ok: bool, confidence: float, reason: string, centerXPct: float, headTopPct: float, headHeightPct: float, eyeYPct: float}|null
     */
    public function analyze(string $inputPath): ?array
    {
        $info = getimagesize($inputPath);
        if (! $info) {
            throw new \RuntimeException('Cannot read image file.');
        }
        $image = $this->loadImage($inputPath, $info[2]);
        $image = $this->fixExifOrientation($image, $inputPath, $info[2]);
        $w = imagesx($image);
        $h = imagesy($image);
        if ($w > 1600 || $h > 1600) {
            $scale = min(1600 / $w, 1600 / $h);
            $nw = (int) ($w * $scale);
            $nh = (int) ($h * $scale);
            $r = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($r, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($image);
            $image = $r;
            $w = $nw;
            $h = $nh;
        }

        $s = $this->detectSubject($image, $w, $h);
        imagedestroy($image);
        if (! $s) {
            return null;
        }

        return [
            'ok' => $s['ok'],
            'confidence' => $s['confidence'],
            'reason' => $s['reason'],
            'centerXPct' => $s['centerX'] / $w * 100,
            'headTopPct' => $s['headTopY'] / $h * 100,
            'headHeightPct' => $s['headHeight'] / $h * 100,
            'eyeYPct' => $s['eyeY'] / $h * 100,
        ];
    }

    /**
     * Detect the subject against the uniform studio backdrop.
     *
     * 1. Model the background from border pixels (handles blue cloth + white/green edges).
     * 2. Build a foreground mask on a downscaled work image via YCbCr chroma distance.
     * 3. Extract head-top, head width/height, and body-center from the silhouette.
     * 4. Optionally refine the eye-line with an in-head skin scan.
     *
     * @return array{ok: bool, confidence: float, reason: string, centerX: float, headTopY: float, headHeight: float, eyeY: float}|null
     */
    private function detectSubject(\GdImage $image, int $w, int $h): ?array
    {
        // --- downscaled work image for fast pixel analysis ---
        $scale = min(1.0, self::WORK_MAX / max($w, $h));
        $ww = max(1, (int) round($w * $scale));
        $wh = max(1, (int) round($h * $scale));
        $work = imagecreatetruecolor($ww, $wh);
        imagecopyresampled($work, $image, 0, 0, 0, 0, $ww, $wh, $w, $h);

        $bg = $this->buildBackgroundModel($work, $ww, $wh);

        // Build foreground mask + per-row horizontal extent within the central band.
        // Central band ignores the far-edge wall so only the standing subject counts.
        $bandL = (int) ($ww * 0.18);
        $bandR = (int) ($ww * 0.82);
        $minRun = max(2, (int) ($ww * 0.03)); // ignore thin speckle rows

        $rowMinX = array_fill(0, $wh, -1);
        $rowMaxX = array_fill(0, $wh, -1);
        $rowCount = array_fill(0, $wh, 0);

        // Bridge small interior gaps (tie/badge), but keep detached edge stripes
        // (wall seams, cloth folds) separate so they don't merge with the subject.
        $gapTol = max(2, (int) ($ww * 0.015));

        for ($y = 0; $y < $wh; $y++) {
            // Find the longest contiguous foreground run within the central band.
            $bestS = $bestE = -1;
            $bestLen = 0;
            $curS = $curE = -1;
            $gap = 0;
            for ($x = $bandL; $x < $bandR; $x++) {
                $rgb = imagecolorat($work, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if ($this->isForeground($r, $g, $b, $bg)) {
                    if ($curS < 0) {
                        $curS = $x;
                    }
                    $curE = $x;
                    $gap = 0;
                } elseif ($curS >= 0) {
                    if (++$gap > $gapTol) {
                        if ($curE - $curS > $bestLen) {
                            $bestLen = $curE - $curS;
                            $bestS = $curS;
                            $bestE = $curE;
                        }
                        $curS = -1;
                    }
                }
            }
            if ($curS >= 0 && $curE - $curS > $bestLen) {
                $bestLen = $curE - $curS;
                $bestS = $curS;
                $bestE = $curE;
            }

            $rowMinX[$y] = $bestS;
            $rowMaxX[$y] = $bestE;
            $rowCount[$y] = $bestLen; // run width used as the solidity measure
        }

        // --- head top: first row (top→down) with a solid foreground run ---
        $headTopY = -1;
        for ($y = 0; $y < (int) ($wh * 0.55); $y++) {
            if ($rowCount[$y] >= $minRun) {
                // require the next row to also be solid (kill single-row noise)
                $next = $y + 1 < $wh ? $rowCount[$y + 1] : 0;
                if ($next >= $minRun) {
                    $headTopY = $y;
                    break;
                }
            }
        }

        if ($headTopY < 0) {
            imagedestroy($work);

            return null;
        }

        // --- scale anchor: head-top → shoulder line (auto-scales with subject distance) ---
        // Build a smoothed central-band width profile below the head top.
        $scanBottom = min($wh - 1, (int) ($headTopY + $wh * 0.42));
        $widths = [];
        for ($y = $headTopY; $y <= $scanBottom; $y++) {
            $widths[$y] = $rowCount[$y] >= $minRun ? ($rowMaxX[$y] - $rowMinX[$y]) : 0;
        }
        // 3-tap smoothing to suppress hair/mask speckle.
        $smooth = [];
        foreach ($widths as $y => $_) {
            $a = $widths[$y - 1] ?? $widths[$y];
            $c = $widths[$y + 1] ?? $widths[$y];
            $smooth[$y] = ($a + $widths[$y] + $c) / 3.0;
        }

        $shoulderWidth = $smooth ? max($smooth) : 0;
        if ($shoulderWidth < $minRun) {
            imagedestroy($work);

            return null;
        }

        // Shoulder line = first row (below a small head margin) reaching 70% of the
        // widest body row. Distance head-top → shoulder is a robust scale anchor.
        $shoulderThresh = 0.70 * $shoulderWidth;
        $headMargin = $headTopY + max(3, (int) ($wh * 0.04));
        $shoulderY = -1;
        for ($y = $headMargin; $y <= $scanBottom; $y++) {
            if (($smooth[$y] ?? 0) >= $shoulderThresh) {
                $shoulderY = $y;
                break;
            }
        }
        if ($shoulderY < 0) {
            $shoulderY = $headTopY + (int) ($wh * 0.18);
        }

        $headNeck = max(1, $shoulderY - $headTopY);
        // Head (crown→chin) is ~80% of the head+neck span down to the shoulder line.
        $headHeight = $headNeck * 0.80;

        // Head width: widest row within the head span (for the skin scan box only).
        $headWidth = 0;
        for ($y = $headTopY; $y <= $shoulderY; $y++) {
            $headWidth = max($headWidth, $widths[$y] ?? 0);
        }
        $headWidth = max($headWidth, $minRun);

        // --- body center: median foreground x across the head band ---
        $headBandBottom = min($wh - 1, (int) ($headTopY + $headHeight));
        $centers = [];
        $solidRows = 0;
        $bandRows = 0;
        for ($y = $headTopY; $y <= $headBandBottom; $y++) {
            $bandRows++;
            if ($rowCount[$y] >= $minRun) {
                $centers[] = ($rowMinX[$y] + $rowMaxX[$y]) / 2.0;
                $solidRows++;
            }
        }
        $centerX = $centers ? $this->median($centers) : $ww / 2.0;
        $occupancy = $bandRows > 0 ? $solidRows / $bandRows : 0.0;

        // --- eye-line: geometric default, refined by in-head skin scan ---
        $eyeY = $headTopY + 0.46 * $headHeight;
        $skinEyeY = $this->skinEyeLine($work, $bg, $centerX, $headTopY, $headWidth, $headHeight);
        if ($skinEyeY !== null) {
            $eyeY = 0.5 * $eyeY + 0.5 * $skinEyeY;
        }

        imagedestroy($work);

        // --- sanity gate: reject implausible detections so we fall back safely ---
        $headHFrac = $headHeight / $wh;
        $centerFrac = $centerX / $ww;
        $shoulderFrac = $shoulderWidth / ($bandR - $bandL);

        $reason = 'ok';
        $ok = true;
        if ($headHFrac < 0.07 || $headHFrac > 0.50) {
            $ok = false;
            $reason = 'head-height out of range';
        } elseif ($centerFrac < 0.28 || $centerFrac > 0.72) {
            $ok = false;
            $reason = 'subject off-center';
        } elseif ($shoulderFrac < 0.18 || $shoulderFrac > 1.0) {
            $ok = false;
            $reason = 'shoulder width out of range';
        } elseif ($occupancy < 0.6) {
            $ok = false;
            $reason = 'silhouette too sparse';
        }

        // Soft confidence: 1.0 when centered with a solid, well-sized silhouette.
        $confidence = $ok
            ? max(0.0, min(1.0, $occupancy)) * (1.0 - min(1.0, abs($centerFrac - 0.5) / 0.22))
            : 0.0;

        // Map work-image coordinates back to the (already 1600-capped) full image.
        $inv = $scale > 0 ? 1.0 / $scale : 1.0;

        return [
            'ok' => $ok,
            'confidence' => $confidence,
            'reason' => $reason,
            'centerX' => $centerX * $inv,
            'headTopY' => $headTopY * $inv,
            'headHeight' => $headHeight * $inv,
            'eyeY' => $eyeY * $inv,
        ];
    }

    /**
     * Model background colors by sampling the border ring (top + side strips).
     * Returns YCbCr cluster centers: list of [Y, Cb, Cr, neutral(bool)].
     *
     * @return list<array{0: float, 1: float, 2: float, 3: bool}>
     */
    private function buildBackgroundModel(\GdImage $img, int $w, int $h): array
    {
        // Sample discrete border regions instead of full strips. Each region is
        // kept only if it looks like backdrop (not skin / not dark hair), so a
        // subject touching the top or a side edge cannot poison the model.
        $regions = [
            [0, 0, 0.10, 0.14],            // top-left corner
            [0.90, 0, 1.00, 0.14],         // top-right corner
            [0.35, 0, 0.65, 0.06],         // top-center
            [0.00, 0.18, 0.06, 0.62],      // mid-left strip
            [0.94, 0.18, 1.00, 0.62],      // mid-right strip
        ];

        $samples = [];
        $rejected = [];
        foreach ($regions as $reg) {
            $x0 = (int) ($reg[0] * $w);
            $y0 = (int) ($reg[1] * $h);
            $x1 = max($x0 + 1, (int) ($reg[2] * $w));
            $y1 = max($y0 + 1, (int) ($reg[3] * $h));

            $px = [];
            $sr = $sg = $sb = $n = 0;
            for ($y = $y0; $y < $y1 && $y < $h; $y += 2) {
                for ($x = $x0; $x < $x1 && $x < $w; $x += 2) {
                    $rgb = imagecolorat($img, $x, $y);
                    $px[] = $this->ycbcr($rgb);
                    $sr += ($rgb >> 16) & 0xFF;
                    $sg += ($rgb >> 8) & 0xFF;
                    $sb += $rgb & 0xFF;
                    $n++;
                }
            }
            if ($n === 0) {
                continue;
            }

            $mr = (int) ($sr / $n);
            $mg = (int) ($sg / $n);
            $mb = (int) ($sb / $n);
            [$my, $mcb, $mcr] = $this->ycbcr(($mr << 16) | ($mg << 8) | $mb);
            $chroma = sqrt(($mcb - 128) ** 2 + ($mcr - 128) ** 2);

            // Reject region resembling subject: skin tone, or dark+near-neutral (hair).
            if ($this->isSkinTone($mr, $mg, $mb) || ($my < 70 && $chroma < 25)) {
                $rejected[] = $px;

                continue;
            }

            foreach ($px as $p) {
                $samples[] = $p;
            }
        }

        // If everything got rejected (unusual), fall back to using all sampled pixels.
        if (! $samples) {
            foreach ($rejected as $px) {
                foreach ($px as $p) {
                    $samples[] = $p;
                }
            }
        }

        // Cluster on the (Cb, Cr) plane using a coarse histogram (bucket size 6).
        $buckets = [];
        foreach ($samples as $s) {
            $bk = ((int) ($s[1] / 6)).','.((int) ($s[2] / 6));
            if (! isset($buckets[$bk])) {
                $buckets[$bk] = ['n' => 0, 'sy' => 0.0, 'scb' => 0.0, 'scr' => 0.0];
            }
            $buckets[$bk]['n']++;
            $buckets[$bk]['sy'] += $s[0];
            $buckets[$bk]['scb'] += $s[1];
            $buckets[$bk]['scr'] += $s[2];
        }

        if (! $buckets) {
            return [[128.0, 128.0, 128.0, true]];
        }

        $total = count($samples);
        arsort($buckets); // by insertion? no — sort by count below instead
        uasort($buckets, fn ($a, $b) => $b['n'] <=> $a['n']);

        $clusters = [];
        foreach ($buckets as $bk) {
            if ($bk['n'] / $total < 0.06 && count($clusters) >= 1) {
                continue; // keep only dominant clusters (always keep the first)
            }
            $cy = $bk['sy'] / $bk['n'];
            $cb = $bk['scb'] / $bk['n'];
            $cr = $bk['scr'] / $bk['n'];
            $neutral = (abs($cb - 128) < 14 && abs($cr - 128) < 14);
            $clusters[] = [$cy, $cb, $cr, $neutral];
            if (count($clusters) >= 3) {
                break;
            }
        }

        // When a chromatic backdrop (blue cloth) is present, drop neutral clusters:
        // they come from white wall at the frame edges and would otherwise swallow
        // the subject's white uniform. The white edges sit outside the central band.
        $hasChromatic = false;
        foreach ($clusters as $c) {
            if (! $c[3]) {
                $hasChromatic = true;
                break;
            }
        }
        if ($hasChromatic) {
            $clusters = array_values(array_filter($clusters, fn ($c) => ! $c[3]));
        }

        return $clusters;
    }

    /**
     * Is this pixel foreground (subject), i.e. far from every background cluster?
     *
     * @param  list<array{0: float, 1: float, 2: float, 3: bool}>  $bg
     */
    private function isForeground(int $r, int $g, int $b, array $bg): bool
    {
        [$y, $cb, $cr] = $this->ycbcr(($r << 16) | ($g << 8) | $b);

        foreach ($bg as $c) {
            $chroma = sqrt(($cb - $c[1]) ** 2 + ($cr - $c[2]) ** 2);
            if ($c[3]) {
                // Neutral (white/grey) backdrop: need both low chroma distance and
                // similar luma to count as background.
                if ($chroma < 12 && abs($y - $c[0]) < 42) {
                    return false;
                }
            } else {
                // Adaptive: a lightly-tinted backdrop sits close to neutral, so a
                // white shirt is only a small chroma step away. Scale the threshold
                // to how saturated the backdrop is, so white uniforms still register
                // as foreground against pale blue cloth.
                $bgChroma = sqrt(($c[1] - 128) ** 2 + ($c[2] - 128) ** 2);
                $threshold = max(9.0, min(self::FG_CHROMA_THRESHOLD, 0.55 * $bgChroma));
                if ($chroma < $threshold) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Scan for skin pixels inside the head box to refine the eye-line Y.
     * Returns a Y (work-image coords) or null when too little skin is found.
     *
     * @param  list<array{0: float, 1: float, 2: float, 3: bool}>  $bg
     */
    private function skinEyeLine(\GdImage $img, array $bg, float $centerX, int $headTopY, float $headWidth, float $headHeight): ?int
    {
        $x0 = max(0, (int) ($centerX - $headWidth * 0.5));
        $x1 = min(imagesx($img) - 1, (int) ($centerX + $headWidth * 0.5));
        $y0 = $headTopY;
        $y1 = min(imagesy($img) - 1, (int) ($headTopY + $headHeight));

        $ys = [];
        for ($y = $y0; $y <= $y1; $y++) {
            for ($x = $x0; $x <= $x1; $x++) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if ($this->isSkinTone($r, $g, $b) && $this->isForeground($r, $g, $b, $bg)) {
                    $ys[] = $y;
                }
            }
        }

        if (count($ys) < 12) {
            return null;
        }

        // Skin centroid sits around the nose/cheeks; eyes are slightly above it.
        $centroid = array_sum($ys) / count($ys);

        return (int) ($centroid - $headHeight * 0.12);
    }

    /**
     * Build the final crop from head metrics so head size/headroom are constant
     * across photos, then center horizontally on the body.
     *
     * @param  array{centerX: float, headTopY: float, headHeight: float, eyeY: float}  $s
     */
    private function cropAroundHead(\GdImage $image, int $imgW, int $imgH, array $s): \GdImage
    {
        $cropH = (int) round($s['headHeight'] / self::HEAD_FRACTION);
        $cropW = (int) round($cropH * self::SLOT_RATIO);

        // Clamp to image, preserving ratio.
        $cropW = min($cropW, $imgW);
        $cropH = min($cropH, $imgH);
        if ($cropW / $cropH > self::SLOT_RATIO) {
            $cropW = (int) round($cropH * self::SLOT_RATIO);
        } else {
            $cropH = (int) round($cropW / self::SLOT_RATIO);
        }

        // Place the top of the head HEADROOM_FRACTION below the crop top.
        $rawCropY = (int) round($s['headTopY'] - self::HEADROOM_FRACTION * $cropH);
        $cropX = (int) round($s['centerX'] - $cropW / 2);
        $cropX = max(0, min($imgW - $cropW, $cropX));

        if ($rawCropY < 0) {
            // Not enough headroom in the source — pad the top with the backdrop color.
            $shift = -$rawCropY;
            $bgColor = $this->edgeBackgroundColor($image, $imgW);
            $padded = imagecreatetruecolor($cropW, $cropH);
            imagefill($padded, 0, 0, $bgColor);
            $srcH = min($cropH - $shift, $imgH);
            imagecopyresampled($padded, $image, 0, $shift, $cropX, 0, $cropW, $srcH, $cropW, $srcH);
            imagedestroy($image);

            return $padded;
        }

        $cropY = max(0, min($imgH - $cropH, $rawCropY));

        $cropped = imagecreatetruecolor($cropW, $cropH);
        imagecopyresampled($cropped, $image, 0, 0, $cropX, $cropY, $cropW, $cropH, $cropW, $cropH);
        imagedestroy($image);

        return $cropped;
    }

    /**
     * Fallback crop when segmentation fails.
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
     * Sample a representative backdrop color from the top edge for padding fills.
     */
    private function edgeBackgroundColor(\GdImage $image, int $imgW): int
    {
        // Sample the two top corners — backdrop even when the head reaches the
        // top-center edge (which the old top-center sampling would mistake for hair).
        $h = imagesy($image);
        $pts = [
            [(int) ($imgW * 0.04), (int) ($h * 0.03)],
            [(int) ($imgW * 0.96), (int) ($h * 0.03)],
            [(int) ($imgW * 0.04), (int) ($h * 0.10)],
            [(int) ($imgW * 0.96), (int) ($h * 0.10)],
        ];
        $rs = $gs = $bs = 0;
        foreach ($pts as [$x, $y]) {
            $rgb = imagecolorat($image, max(0, min($imgW - 1, $x)), max(0, min($h - 1, $y)));
            $rs += ($rgb >> 16) & 0xFF;
            $gs += ($rgb >> 8) & 0xFF;
            $bs += $rgb & 0xFF;
        }
        $n = count($pts);

        return imagecolorallocate($image, (int) ($rs / $n), (int) ($gs / $n), (int) ($bs / $n));
    }

    /**
     * Convert a packed RGB int to [Y, Cb, Cr] (0–255 range).
     *
     * @return array{0: float, 1: float, 2: float}
     */
    private function ycbcr(int $rgb): array
    {
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        $y = 0.299 * $r + 0.587 * $g + 0.114 * $b;
        $cb = 128 - 0.168736 * $r - 0.331264 * $g + 0.5 * $b;
        $cr = 128 + 0.5 * $r - 0.418688 * $g - 0.081312 * $b;

        return [$y, $cb, $cr];
    }

    /**
     * @param  list<float>  $values
     */
    private function median(array $values): float
    {
        sort($values);
        $n = count($values);
        if ($n === 0) {
            return 0.0;
        }
        $mid = (int) ($n / 2);

        return $n % 2 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2.0;
    }

    /**
     * Check if a pixel color matches skin tone range (YCbCr ellipse-ish gate).
     */
    private function isSkinTone(int $r, int $g, int $b): bool
    {
        [$y, $cb, $cr] = $this->ycbcr(($r << 16) | ($g << 8) | $b);

        // Standard YCbCr skin gate, robust across light/dark skin.
        if ($cb < 77 || $cb > 135) {
            return false;
        }
        if ($cr < 133 || $cr > 180) {
            return false;
        }
        if ($y < 40 || $y > 245) {
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

    /**
     * Raise PHP memory_limit to at least the given MB (never lowers it).
     */
    private function ensureMemoryLimit(int $mb): void
    {
        $current = trim((string) ini_get('memory_limit'));
        if ($current === '' || $current === '-1') {
            return; // already unlimited
        }

        $unit = strtolower(substr($current, -1));
        $value = (int) $current;
        $bytes = match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => (int) $current,
        };

        if ($bytes < $mb * 1024 * 1024) {
            @ini_set('memory_limit', $mb.'M');
        }
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
