<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class ImageConverter
{
    /**
     * Store an uploaded image as WebP.
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

        $image = Image::read($file);

        if ($maxWidth && $image->width() > $maxWidth) {
            $image->scaleDown(width: $maxWidth);
        }

        $encoded = $image->toWebp($quality);

        Storage::disk($disk)->put($path, (string) $encoded);

        return $path;
    }
}
