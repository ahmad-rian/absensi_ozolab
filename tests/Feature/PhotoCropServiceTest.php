<?php

use App\Services\PhotoCropService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    @ini_set('memory_limit', '512M');
    Storage::fake('public');
});

// Reference + the 15 studio test cases, as single-arg dataset rows.
$cropFixtures = (function (): array {
    $base = dirname(__DIR__).'/fixtures/photos';
    $files = [$base.'/reference.jpg'];
    foreach (glob($base.'/cases/*.jpeg') ?: [] as $f) {
        $files[] = $f;
    }

    return array_map(fn ($f) => [$f], $files);
})();

it('crops every fixture to the 16:21 card ratio at min resolution', function (string $src) {
    $service = new PhotoCropService;
    $path = 'photos/test/'.basename($src).'.png';

    $service->cropAndStore($src, $path);

    expect(Storage::disk('public')->exists($path))->toBeTrue();

    $full = Storage::disk('public')->path($path);
    [$w, $h] = getimagesize($full);

    // Aspect ratio within 1% of 16:21.
    expect($w / $h)->toBeBetween(16 / 21 * 0.99, 16 / 21 * 1.01);
    // Minimum output resolution honored.
    expect($w)->toBeGreaterThanOrEqual(480);
    expect($h)->toBeGreaterThanOrEqual(630);
})->with($cropFixtures);

it('leaves headroom above the head (top is backdrop, not clipped hair/face)', function (string $src) {
    $service = new PhotoCropService;
    $path = 'photos/test/hr_'.basename($src).'.png';

    $service->cropAndStore($src, $path);
    $img = imagecreatefrompng(Storage::disk('public')->path($path));
    $w = imagesx($img);
    $h = imagesy($img);

    // Sample the top-center strip — should be studio backdrop, never dark hair.
    $lum = [];
    for ($i = 3; $i <= 5; $i++) {
        $rgb = imagecolorat($img, (int) ($w * $i / 8), (int) ($h * 0.025));
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        $lum[] = 0.299 * $r + 0.587 * $g + 0.114 * $b;
    }
    imagedestroy($img);

    // Hair/scalp would be dark (low luma); backdrop (blue/white) is bright.
    expect(max($lum))->toBeGreaterThan(70);
})->with($cropFixtures);

it('actually contains a subject (not an empty backdrop crop)', function (string $src) {
    $service = new PhotoCropService;
    $path = 'photos/test/subj_'.basename($src).'.png';

    $service->cropAndStore($src, $path);
    $img = imagecreatefrompng(Storage::disk('public')->path($path));
    $w = imagesx($img);
    $h = imagesy($img);

    // Backdrop reference = top-center pixel (always studio backdrop).
    $bg = imagecolorat($img, (int) ($w / 2), (int) ($h * 0.02));
    $br = ($bg >> 16) & 0xFF;
    $bgrn = ($bg >> 8) & 0xFF;
    $bb = $bg & 0xFF;

    // Count pixels in the central column that differ strongly from the backdrop.
    $total = 0;
    $subject = 0;
    for ($y = (int) ($h * 0.15); $y < $h; $y += 4) {
        for ($x = (int) ($w * 0.30); $x < (int) ($w * 0.70); $x += 4) {
            $rgb = imagecolorat($img, $x, $y);
            $dr = (($rgb >> 16) & 0xFF) - $br;
            $dg = (($rgb >> 8) & 0xFF) - $bgrn;
            $db = ($rgb & 0xFF) - $bb;
            if (sqrt($dr * $dr + $dg * $dg + $db * $db) > 60) {
                $subject++;
            }
            $total++;
        }
    }

    // A real head+shoulders crop fills a large share of the central column.
    expect($subject / max(1, $total))->toBeGreaterThan(0.35);
})->with($cropFixtures);

it('produces a consistent, reference-matching frame across all studio fixtures', function () use ($cropFixtures) {
    $service = new PhotoCropService;

    $centerX = [];
    $headTop = [];
    $eyeY = [];
    foreach ($cropFixtures as [$src]) {
        $m = $service->analyze($src);
        expect($m)->not->toBeNull();
        expect($m['ok'])->toBeTrue("detection failed for {$src}: {$m['reason']}");
        expect($m['confidence'])->toBeGreaterThan(0.6);
        // Every subject is centered, with the head in the upper portion.
        expect($m['centerXPct'])->toBeBetween(40, 60);
        expect($m['headTopPct'])->toBeBetween(10, 35);
        $centerX[] = $m['centerXPct'];
        $headTop[] = $m['headTopPct'];
        $eyeY[] = $m['eyeYPct'];
    }

    $stdev = function (array $x): float {
        $m = array_sum($x) / count($x);

        return sqrt(array_sum(array_map(fn ($v) => ($v - $m) ** 2, $x)) / count($x));
    };

    // Tight spread = framing is uniform and matches the reference look.
    expect($stdev($centerX))->toBeLessThan(3.0);
    expect($stdev($headTop))->toBeLessThan(4.0);
    expect($stdev($eyeY))->toBeLessThan(4.0);
});

// ---- Robustness: synthetic edge cases never crash and never emit garbage ----

if (! function_exists('makeStudioImage')) {
    function makeStudioImage(int $w, int $h, callable $drawSubject, array $bg = [40, 150, 230]): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, $bg[0], $bg[1], $bg[2]));
        $drawSubject($im, $w, $h);
        $path = tempnam(sys_get_temp_dir(), 'crop').'.png';
        imagepng($im, $path);
        imagedestroy($im);

        return $path;
    }
}

if (! function_exists('expectValidCardCrop')) {
    function expectValidCardCrop(string $full): void
    {
        [$w, $h] = getimagesize($full);
        expect($w / $h)->toBeBetween(16 / 21 * 0.99, 16 / 21 * 1.01);
        expect($w)->toBeGreaterThanOrEqual(480);
        expect($h)->toBeGreaterThanOrEqual(630);
    }
}

it('off-center subject is rejected and falls back to a valid crop', function () {
    $src = makeStudioImage(800, 600, function ($im, $w, $h) {
        $skin = imagecolorallocate($im, 150, 110, 90);
        imagefilledellipse($im, (int) ($w * 0.84), (int) ($h * 0.35), 90, 110, $skin);
        imagefilledrectangle($im, (int) ($w * 0.72), (int) ($h * 0.5), (int) ($w * 0.96), $h, imagecolorallocate($im, 240, 240, 240));
    });

    $service = new PhotoCropService;
    $m = $service->analyze($src);
    expect($m['ok'])->toBeFalse();

    $service->cropAndStore($src, 'photos/test/off_center.png');
    expectValidCardCrop(Storage::disk('public')->path('photos/test/off_center.png'));
    @unlink($src);
});

it('no-contrast (white subject on white background) falls back without garbage', function () {
    $src = makeStudioImage(800, 600, function ($im, $w, $h) {
        // near-white body on white backdrop = no separation
        imagefilledrectangle($im, (int) ($w * 0.35), (int) ($h * 0.4), (int) ($w * 0.65), $h, imagecolorallocate($im, 248, 248, 248));
    }, [255, 255, 255]);

    $service = new PhotoCropService;
    $m = $service->analyze($src);
    // Either no detection or a rejected one — never a confident bogus subject.
    expect($m === null || ! $m['ok'])->toBeTrue();

    $service->cropAndStore($src, 'photos/test/no_contrast.png');
    expectValidCardCrop(Storage::disk('public')->path('photos/test/no_contrast.png'));
    @unlink($src);
});

it('subject touching the top edge still detects and crops validly', function () {
    $src = makeStudioImage(800, 600, function ($im, $w, $h) {
        $hair = imagecolorallocate($im, 30, 25, 22);
        $skin = imagecolorallocate($im, 150, 110, 90);
        // head starts at the very top of the frame
        imagefilledrectangle($im, (int) ($w * 0.40), 0, (int) ($w * 0.60), (int) ($h * 0.12), $hair);
        imagefilledellipse($im, (int) ($w * 0.50), (int) ($h * 0.22), 130, 150, $skin);
        imagefilledrectangle($im, (int) ($w * 0.32), (int) ($h * 0.40), (int) ($w * 0.68), $h, imagecolorallocate($im, 240, 240, 240));
    });

    $service = new PhotoCropService;
    $service->cropAndStore($src, 'photos/test/touch_top.png');
    expectValidCardCrop(Storage::disk('public')->path('photos/test/touch_top.png'));
    @unlink($src);
});

it('throws a clear error on an unreadable / unsupported file', function () {
    $txt = tempnam(sys_get_temp_dir(), 'crop').'.txt';
    file_put_contents($txt, 'this is not an image');

    $service = new PhotoCropService;
    expect(fn () => $service->cropAndStore($txt, 'photos/test/bad.png'))
        ->toThrow(RuntimeException::class);
    @unlink($txt);
});
