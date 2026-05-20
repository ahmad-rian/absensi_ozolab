<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageConverter
{
    /**
     * Store an uploaded image as WebP using native PHP GD.
     *
     * @return string The stored path (relative to disk root).
     */
    public function storeAsWebp(
        UploadedFile $file,
        string $directory = 'images',
        string $disk = 'public',
        int $quality = 85,
        ?int $maxWidth = 1200,
    ): string {
        $filename = Str::ulid().'.webp';
        $path = trim($directory, '/').'/'.$filename;

        $source = $this->createImageFromFile($file->getPathname(), $file->getMimeType());

        if (! $source) {
            // Fallback: store original file as-is if GD can't process
            $path = trim($directory, '/').'/'.Str::ulid().'.'.$file->getClientOriginalExtension();
            Storage::disk($disk)->put($path, file_get_contents($file->getPathname()));

            return $path;
        }

        // Resize if needed
        $width = imagesx($source);
        $height = imagesy($source);

        if ($maxWidth && $width > $maxWidth) {
            $newHeight = (int) round($height * ($maxWidth / $width));
            $resized = imagecreatetruecolor($maxWidth, $newHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $maxWidth, $newHeight, $width, $height);
            imagedestroy($source);
            $source = $resized;
        }

        // Encode to WebP
        ob_start();
        imagewebp($source, null, $quality);
        $webpData = ob_get_clean();
        imagedestroy($source);

        Storage::disk($disk)->put($path, $webpData);

        return $path;
    }

    private function createImageFromFile(string $filepath, ?string $mime): ?\GdImage
    {
        return match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($filepath) ?: null,
            'image/png' => $this->createFromPng($filepath),
            'image/gif' => @imagecreatefromgif($filepath) ?: null,
            'image/webp' => @imagecreatefromwebp($filepath) ?: null,
            'image/bmp' => @imagecreatefrombmp($filepath) ?: null,
            default => null,
        };
    }

    private function createFromPng(string $filepath): ?\GdImage
    {
        $img = @imagecreatefrompng($filepath);
        if (! $img) {
            return null;
        }
        imagealphablending($img, true);
        imagesavealpha($img, true);

        return $img;
    }
}
