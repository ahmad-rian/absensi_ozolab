<?php

namespace App\Support;

use Spatie\Browsershot\Browsershot;

/**
 * Berkas siap cetak: JPEG berkualitas tinggi yang MENYEBUT resolusinya.
 *
 * Tangkapan layar Chrome tidak menyimpan DPI — berkasnya terbaca 72 DPI.
 * Kartu 1349 px pada "72 DPI" dianggap selebar 47 cm, sehingga aplikasi
 * cetak mengecilkannya ulang (resample) dan teks jadi lembek. Dengan DPI 400
 * tertulis di berkas, ukuran cetaknya langsung 85,6 × 54 mm tanpa resample.
 *
 * JPEG kualitas 95 juga 60–75% lebih kecil dari PNG untuk gambar yang
 * memuat foto, tanpa kehilangan pixel resolusi.
 */
class GambarCetak
{
    public const DPI = 400;

    public const KUALITAS = 95;

    public const EKSTENSI = 'jpg';

    public const MIME = 'image/jpeg';

    /** Simpan hasil Browsershot langsung sebagai JPEG cetak di `$tujuan`. */
    public static function simpan(Browsershot $browsershot, string $tujuan): void
    {
        $png = tempnam(sys_get_temp_dir(), 'cetak-').'.png';

        try {
            $browsershot->save($png);
            self::dariPng($png, $tujuan);
        } finally {
            @unlink($png);
        }
    }

    /**
     * Ubah PNG menjadi JPEG cetak. Area transparan diratakan ke putih —
     * JPEG tidak punya alpha, dan GD akan mengubahnya menjadi hitam.
     */
    public static function dariPng(string $png, string $tujuan): void
    {
        if (extension_loaded('imagick')) {
            self::denganImagick($png, $tujuan);

            return;
        }

        self::denganGd($png, $tujuan);
    }

    /** MIME berkas menurut ekstensinya; berkas lama tetap PNG. */
    public static function mime(string $path): string
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg'], true) ? self::MIME : 'image/png';
    }

    /** Imagick bisa menyimpan warna 4:4:4 — teks berwarna tetap tajam di tepinya. */
    public static function denganImagick(string $png, string $tujuan): void
    {
        $gambar = new \Imagick($png);

        try {
            $gambar->setImageBackgroundColor('white');
            $rata = $gambar->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            $rata->setImageFormat('jpeg');
            $rata->stripImage();
            $rata->setImageCompressionQuality(self::KUALITAS);
            $rata->setSamplingFactors(['1x1', '1x1', '1x1']);
            $rata->setImageUnits(\Imagick::RESOLUTION_PIXELSPERINCH);
            $rata->setImageResolution(self::DPI, self::DPI);

            if (! $rata->writeImage($tujuan)) {
                throw new \RuntimeException('Gagal menulis JPEG cetak.');
            }

            $rata->destroy();
        } finally {
            $gambar->destroy();
        }
    }

    public static function denganGd(string $png, string $tujuan): void
    {
        $sumber = @imagecreatefrompng($png);

        if ($sumber === false) {
            throw new \RuntimeException('Hasil render bukan PNG yang bisa dibaca.');
        }

        $lebar = imagesx($sumber);
        $tinggi = imagesy($sumber);
        $kanvas = imagecreatetruecolor($lebar, $tinggi);
        imagefill($kanvas, 0, 0, imagecolorallocate($kanvas, 255, 255, 255));
        imagecopy($kanvas, $sumber, 0, 0, 0, 0, $lebar, $tinggi);
        imageresolution($kanvas, self::DPI, self::DPI);

        try {
            if (! imagejpeg($kanvas, $tujuan, self::KUALITAS)) {
                throw new \RuntimeException('Gagal menulis JPEG cetak.');
            }
        } finally {
            imagedestroy($sumber);
            imagedestroy($kanvas);
        }
    }
}
