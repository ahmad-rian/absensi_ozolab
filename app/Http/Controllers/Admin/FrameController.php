<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolFrame;
use App\Services\ImageConverter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class FrameController extends Controller
{
    public function index(Request $request): Response
    {
        $frames = SchoolFrame::forSchool()
            ->when($request->category, fn ($q, $cat) => $q->where('category', $cat))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/frames/index', [
            'frames' => $frames->map(fn (SchoolFrame $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'image_url' => Storage::disk('public')->url($f->image_path),
                'width' => $f->width,
                'height' => $f->height,
                'category' => $f->category,
                'is_active' => $f->is_active,
                'sort_order' => $f->sort_order,
            ]),
            'filters' => [
                'category' => $request->category ?? '',
            ],
        ]);
    }

    public function store(Request $request, ImageConverter $converter): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'image' => ['required', 'image', 'max:5120'],
            'category' => ['required', 'string', 'in:osis,perpustakaan,album'],
        ]);

        $path = $converter->storeAsWebp(
            $request->file('image'),
            'frames',
            'public',
            90,
            2000,
        );

        // Auto-crop white borders from frame image
        $fullPath = Storage::disk('public')->path($path);
        $this->autoCropWhiteBorders($fullPath);

        [$width, $height] = getimagesize($fullPath) ?: [0, 0];

        SchoolFrame::create([
            'name' => $validated['name'],
            'image_path' => $path,
            'width' => $width,
            'height' => $height,
            'category' => $validated['category'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Frame berhasil ditambahkan.']);

        return to_route('admin.frames');
    }

    public function update(Request $request, SchoolFrame $frame): RedirectResponse
    {
        abort_unless($frame->school_id === auth()->user()->school_id, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:osis,perpustakaan,album'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $frame->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Frame berhasil diupdate.']);

        return to_route('admin.frames');
    }

    /**
     * Auto-crop white/near-white borders from a frame image.
     */
    private function autoCropWhiteBorders(string $path): void
    {
        $info = getimagesize($path);
        if (! $info) {
            return;
        }

        $image = match ($info[2]) {
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            default => null,
        };

        if (! $image) {
            return;
        }

        $w = imagesx($image);
        $h = imagesy($image);
        $threshold = 245; // pixels with R,G,B all above this are "white"

        $isWhite = function (int $x, int $y) use ($image, $threshold): bool {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;

            return $r >= $threshold && $g >= $threshold && $b >= $threshold;
        };

        // Scan from each edge to find first non-white row/column
        $step = max(1, (int) ($w / 100)); // sample every few pixels for speed
        $top = 0;
        $bottom = $h - 1;
        $left = 0;
        $right = $w - 1;

        // Top edge
        for ($y = 0; $y < $h / 2; $y++) {
            $allWhite = true;
            for ($x = 0; $x < $w; $x += $step) {
                if (! $isWhite($x, $y)) {
                    $allWhite = false;
                    break;
                }
            }
            if (! $allWhite) {
                $top = $y;
                break;
            }
        }

        // Bottom edge
        for ($y = $h - 1; $y > $h / 2; $y--) {
            $allWhite = true;
            for ($x = 0; $x < $w; $x += $step) {
                if (! $isWhite($x, $y)) {
                    $allWhite = false;
                    break;
                }
            }
            if (! $allWhite) {
                $bottom = $y;
                break;
            }
        }

        // Left edge
        for ($x = 0; $x < $w / 2; $x++) {
            $allWhite = true;
            for ($y = $top; $y <= $bottom; $y += $step) {
                if (! $isWhite($x, $y)) {
                    $allWhite = false;
                    break;
                }
            }
            if (! $allWhite) {
                $left = $x;
                break;
            }
        }

        // Right edge
        for ($x = $w - 1; $x > $w / 2; $x--) {
            $allWhite = true;
            for ($y = $top; $y <= $bottom; $y += $step) {
                if (! $isWhite($x, $y)) {
                    $allWhite = false;
                    break;
                }
            }
            if (! $allWhite) {
                $right = $x;
                break;
            }
        }

        $cropW = $right - $left + 1;
        $cropH = $bottom - $top + 1;

        // Only crop if we actually trimmed something significant (>2% per side)
        $minTrim = (int) ($w * 0.02);
        if ($top < $minTrim && $left < $minTrim && ($w - $right) < $minTrim && ($h - $bottom) < $minTrim) {
            imagedestroy($image);

            return;
        }

        $cropped = imagecreatetruecolor($cropW, $cropH);
        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        imagecopyresampled($cropped, $image, 0, 0, $left, $top, $cropW, $cropH, $cropW, $cropH);
        imagedestroy($image);

        imagewebp($cropped, $path, 90);
        imagedestroy($cropped);
    }

    public function destroy(SchoolFrame $frame): RedirectResponse
    {
        abort_unless($frame->school_id === auth()->user()->school_id, 403);

        if ($frame->image_path && Storage::disk('public')->exists($frame->image_path)) {
            Storage::disk('public')->delete($frame->image_path);
        }

        $frame->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Frame berhasil dihapus.']);

        return to_route('admin.frames');
    }
}
