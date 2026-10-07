<?php

use App\Support\GambarCetak;

/** PNG uji dengan sudut transparan, seperti tangkapan layar kartu bersudut bulat. */
function pngKartuUji(): string
{
    $png = tempnam(sys_get_temp_dir(), 'uji-').'.png';
    $gambar = imagecreatetruecolor(1349, 850);
    imagesavealpha($gambar, true);
    imagefill($gambar, 0, 0, imagecolorallocatealpha($gambar, 0, 0, 0, 127));
    imagefilledrectangle($gambar, 40, 40, 1300, 800, imagecolorallocate($gambar, 47, 127, 208));
    imagepng($gambar, $png);
    imagedestroy($gambar);

    return $png;
}

/** @return array{0: int, 1: int, 2: int, 3: int} lebar, tinggi, dpi-x, dpi-y dari header JFIF */
function bacaJpegCetak(string $path): array
{
    [$lebar, $tinggi, $jenis] = getimagesize($path);
    expect($jenis)->toBe(IMAGETYPE_JPEG);
    $kepala = file_get_contents($path, false, null, 0, 20);
    expect(substr($kepala, 6, 4))->toBe('JFIF')
        ->and(ord($kepala[13]))->toBe(1);

    return [$lebar, $tinggi, unpack('n', substr($kepala, 14, 2))[1], unpack('n', substr($kepala, 16, 2))[1]];
}

test('JPEG cetak lewat GD menyimpan resolusi penuh dan DPI 400, sudut transparan jadi putih', function () {
    $png = pngKartuUji();
    $jpg = tempnam(sys_get_temp_dir(), 'uji-').'.jpg';

    GambarCetak::denganGd($png, $jpg);

    expect(bacaJpegCetak($jpg))->toBe([1349, 850, 400, 400]);
    $hasil = imagecreatefromjpeg($jpg);
    $sudut = imagecolorsforindex($hasil, imagecolorat($hasil, 2, 2));
    expect($sudut['red'])->toBeGreaterThan(245)->and($sudut['blue'])->toBeGreaterThan(245);
    @unlink($png);
    @unlink($jpg);
});

test('JPEG cetak lewat Imagick menyimpan resolusi penuh dan DPI 400', function () {
    $png = pngKartuUji();
    $jpg = tempnam(sys_get_temp_dir(), 'uji-').'.jpg';

    GambarCetak::denganImagick($png, $jpg);

    expect(bacaJpegCetak($jpg))->toBe([1349, 850, 400, 400]);
    @unlink($png);
    @unlink($jpg);
})->skip(! extension_loaded('imagick'), 'Imagick tidak terpasang');

test('mime mengikuti ekstensi supaya berkas PNG lama tetap terlayani', function () {
    expect(GambarCetak::mime('cards/a/x-osis.jpg'))->toBe('image/jpeg')
        ->and(GambarCetak::mime('cards/a/x-osis.png'))->toBe('image/png');
});
