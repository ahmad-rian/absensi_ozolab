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

        // Get image dimensions
        $fullPath = Storage::disk('public')->path($path);
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
