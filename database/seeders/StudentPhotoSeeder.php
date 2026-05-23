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
    /**
     * Generate a 3:4 portrait avatar with initials.
     *
     * @param  int[]  $color  RGB array
     */
    private function generateAvatar(string $initials, array $color, bool $isMale): \GdImage
    {
        $w = 300;
        $h = 400; // 3:4 portrait ratio

        $image = imagecreatetruecolor($w, $h);
        imagesavealpha($image, true);

        // Gradient background
        for ($y = 0; $y < $h; $y++) {
            $ratio = $y / $h;
            $r = (int) min(255, $color[0] + 40 * $ratio);
            $g = (int) min(255, $color[1] + 40 * $ratio);
            $b = (int) min(255, $color[2] + 40 * $ratio);
            $lineColor = imagecolorallocate($image, $r, $g, $b);
            imageline($image, 0, $y, $w - 1, $y, $lineColor);
        }

        // Circle overlay (head silhouette area)
        $semiWhite = imagecolorallocatealpha($image, 255, 255, 255, 110);
        imagefilledellipse($image, (int) ($w / 2), (int) ($h * 0.38), (int) ($w * 0.7), (int) ($w * 0.7), $semiWhite);

        // Draw initials centered in upper area (where "face" would be)
        $white = imagecolorallocate($image, 255, 255, 255);
        $fontSize = 5;
        $charWidth = imagefontwidth($fontSize);
        $charHeight = imagefontheight($fontSize);
        $scale = 7;
        $totalTextW = strlen($initials) * $charWidth * $scale;
        $startX = (int) (($w - $totalTextW) / 2);
        $startY = (int) ($h * 0.38 - $charHeight * $scale / 2);

        for ($i = 0; $i < strlen($initials); $i++) {
            $charImg = imagecreatetruecolor($charWidth, $charHeight);
            imagefill($charImg, 0, 0, imagecolorallocate($charImg, 0, 0, 0));
            imagestring($charImg, $fontSize, 0, 0, $initials[$i], imagecolorallocate($charImg, 255, 255, 255));
            $scaledChar = imagecreatetruecolor($charWidth * $scale, $charHeight * $scale);
            imagecopyresized($scaledChar, $charImg, 0, 0, 0, 0, $charWidth * $scale, $charHeight * $scale, $charWidth, $charHeight);

            for ($px = 0; $px < $charWidth * $scale; $px++) {
                for ($py = 0; $py < $charHeight * $scale; $py++) {
                    if (imagecolorat($scaledChar, $px, $py) > 0) {
                        imagesetpixel($image, $startX + $i * $charWidth * $scale + $px, $startY + $py, $white);
                    }
                }
            }
            imagedestroy($charImg);
            imagedestroy($scaledChar);
        }

        // Body silhouette (shoulders) at bottom
        $bodySemi = imagecolorallocatealpha($image, 255, 255, 255, 118);
        imagefilledellipse($image, (int) ($w / 2), (int) ($h * 0.95), (int) ($w * 1.1), (int) ($h * 0.45), $bodySemi);

        return $image;
    }
}
