<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
            // Fallback: simpan berkas asli apa adanya kalau GD tidak bisa
            // mengolahnya. Ekstensinya diambil dari hasil sniffing mime, BUKAN
            // dari nama berkas klien — berkas ber-signature PNG palsu bernama
            // `x.html` dulu tersimpan sebagai .html dan disajikan sebagai HTML
            // same-origin.
            $extension = match ($file->getMimeType()) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                'image/bmp' => 'bmp',
                default => throw ValidationException::withMessages([
                    'file' => 'Format gambar tidak didukung.',
                ]),
            };

            $path = trim($directory, '/').'/'.Str::ulid().'.'.$extension;
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
