<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StudentPhotoSeeder extends Seeder
{
    /**
     * Generate placeholder student photos using GD.
     * Creates colored avatar images with initials — no external API needed.
     */
    public function run(): void
    {
        $students = Student::whereNull('photo_path')->get();

        if ($students->isEmpty()) {
            $this->command->info('No students without photos found.');

            return;
        }

        $dir = 'photos/students';
        Storage::disk('public')->makeDirectory($dir);

        $colors = [
            [59, 130, 246],   // blue
            [16, 185, 129],   // green
            [245, 158, 11],   // amber
            [239, 68, 68],    // red
            [139, 92, 246],   // violet
            [236, 72, 153],   // pink
            [14, 165, 233],   // sky
            [34, 197, 94],    // emerald
            [249, 115, 22],   // orange
            [168, 85, 247],   // purple
        ];

        $count = 0;

        foreach ($students as $student) {
            $initials = $this->getInitials($student->full_name);
            $color = $colors[$student->id % count($colors)];
            $isMale = $student->gender === Gender::LakiLaki;

            $image = $this->generateAvatar($initials, $color, $isMale);

            $filename = sprintf('%s/%d-%s.webp', $dir, $student->id, Str::slug($student->full_name));
            $fullPath = Storage::disk('public')->path($filename);

            imagewebp($image, $fullPath, 85);
            imagedestroy($image);

            $student->update(['photo_path' => $filename]);
            $count++;
        }

        $this->command->info("Generated {$count} student photos.");
    }

    private function getInitials(string $name): string
    {
        $parts = explode(' ', trim($name));
        $initials = strtoupper(substr($parts[0], 0, 1));
        if (count($parts) > 1) {
            $initials .= strtoupper(substr(end($parts), 0, 1));
        }

        return $initials;
    }

    /**
     * @param  int[]  $color  RGB array
     */
    private function generateAvatar(string $initials, array $color, bool $isMale): \GdImage
    {
        $size = 400;
        $image = imagecreatetruecolor($size, $size);

        // Background gradient
        $bgColor = imagecolorallocate($image, $color[0], $color[1], $color[2]);
        $lightBg = imagecolorallocate(
            $image,
            min(255, $color[0] + 40),
            min(255, $color[1] + 40),
            min(255, $color[2] + 40),
        );

        // Fill with gradient effect
        for ($y = 0; $y < $size; $y++) {
            $ratio = $y / $size;
            $r = (int) ($color[0] + ($color[0] + 40 - $color[0]) * $ratio);
            $g = (int) ($color[1] + ($color[1] + 40 - $color[1]) * $ratio);
            $b = (int) ($color[2] + ($color[2] + 40 - $color[2]) * $ratio);
            $lineColor = imagecolorallocate($image, min(255, $r), min(255, $g), min(255, $b));
            imageline($image, 0, $y, $size - 1, $y, $lineColor);
        }

        // White text for initials
        $white = imagecolorallocate($image, 255, 255, 255);
        $fontSize = 5; // Built-in font (largest)

        $textWidth = imagefontwidth($fontSize) * strlen($initials);
        $textHeight = imagefontheight($fontSize);

        // Scale up: draw large text centered
        $x = (int) (($size - $textWidth * 8) / 2);
        $y = (int) (($size - $textHeight * 8) / 2);

        // Draw initials multiple times for thickness (poor man's bold)
        for ($sx = -1; $sx <= 1; $sx++) {
            for ($sy = -1; $sy <= 1; $sy++) {
                $this->drawScaledString($image, $initials, $size, $white, $sx, $sy);
            }
        }

        // Add subtle circle overlay
        $semiWhite = imagecolorallocatealpha($image, 255, 255, 255, 115);
        imagefilledellipse($image, $size / 2, $size / 2, (int) ($size * 0.85), (int) ($size * 0.85), $semiWhite);

        // Redraw initials on top of circle
        $this->drawScaledString($image, $initials, $size, $white, 0, 0);

        return $image;
    }

    private function drawScaledString(\GdImage $image, string $text, int $size, int $color, int $offsetX, int $offsetY): void
    {
        $fontSize = 5;
        $charWidth = imagefontwidth($fontSize);
        $charHeight = imagefontheight($fontSize);
        $scale = 8;

        $totalWidth = strlen($text) * $charWidth * $scale;
        $startX = (int) (($size - $totalWidth) / 2) + $offsetX;
        $startY = (int) (($size - $charHeight * $scale) / 2) + $offsetY;

        for ($i = 0; $i < strlen($text); $i++) {
            $charImg = imagecreatetruecolor($charWidth, $charHeight);
            $black = imagecolorallocate($charImg, 0, 0, 0);
            $white = imagecolorallocate($charImg, 255, 255, 255);
            imagefill($charImg, 0, 0, $black);
            imagestring($charImg, $fontSize, 0, 0, $text[$i], $white);

            $scaledChar = imagecreatetruecolor($charWidth * $scale, $charHeight * $scale);
            imagecopyresized($scaledChar, $charImg, 0, 0, 0, 0, $charWidth * $scale, $charHeight * $scale, $charWidth, $charHeight);

            // Copy non-black pixels
            for ($px = 0; $px < $charWidth * $scale; $px++) {
                for ($py = 0; $py < $charHeight * $scale; $py++) {
                    $pixelColor = imagecolorat($scaledChar, $px, $py);
                    if ($pixelColor > 0) {
                        imagesetpixel($image, $startX + $i * $charWidth * $scale + $px, $startY + $py, $color);
                    }
                }
            }

            imagedestroy($charImg);
            imagedestroy($scaledChar);
        }
    }
}
