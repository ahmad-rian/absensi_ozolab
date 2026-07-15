<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolCardLayout;
use App\Models\SchoolFrame;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CardLayoutController extends Controller
{
    public function index(): Response
    {
        $layouts = SchoolCardLayout::forSchool()
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/card-layouts/index', [
            'layouts' => $layouts->map(fn (SchoolCardLayout $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'type' => $l->type,
                'is_default' => $l->is_default,
                'is_active' => $l->is_active,
                'layout_config' => $l->layout_config,
            ]),
        ]);
    }

    public function create(): Response
    {
        $frames = SchoolFrame::forSchool()
            ->whereIn('category', ['osis', 'perpustakaan'])
            ->where('is_active', true)
            ->get(['id', 'name', 'image_path', 'width', 'height', 'category']);

        return Inertia::render('admin/card-layouts/editor', [
            'layout' => null,
            'defaultElements' => SchoolCardLayout::defaultElements(),
            'frames' => $frames->map(fn (SchoolFrame $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'image_url' => Storage::disk('public')->url($f->image_path),
                'width' => $f->width,
                'height' => $f->height,
                'category' => $f->category,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:osis,perpustakaan,identitas'],
            'layout_config' => ['required', 'array'],
            'is_default' => ['boolean'],
        ]);

        if ($validated['is_default'] ?? false) {
            SchoolCardLayout::forSchool()
                ->where('type', $validated['type'])
                ->update(['is_default' => false]);
        }

        SchoolCardLayout::create($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Layout kartu berhasil disimpan.']);

        return to_route('admin.card-layouts');
    }

    public function edit(SchoolCardLayout $cardLayout): Response
    {
        abort_unless($cardLayout->school_id === auth()->user()->school_id, 403);

        $frames = SchoolFrame::forSchool()
            ->whereIn('category', ['osis', 'perpustakaan'])
            ->where('is_active', true)
            ->get(['id', 'name', 'image_path', 'width', 'height', 'category']);

        return Inertia::render('admin/card-layouts/editor', [
            'layout' => [
                'id' => $cardLayout->id,
                'name' => $cardLayout->name,
                'type' => $cardLayout->type,
                'is_default' => $cardLayout->is_default,
                'is_active' => $cardLayout->is_active,
                'layout_config' => $cardLayout->normalizedConfig(),
            ],
            'defaultElements' => SchoolCardLayout::defaultElements(),
            'frames' => $frames->map(fn (SchoolFrame $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'image_url' => Storage::disk('public')->url($f->image_path),
                'width' => $f->width,
                'height' => $f->height,
                'category' => $f->category,
            ]),
        ]);
    }

    public function update(Request $request, SchoolCardLayout $cardLayout): RedirectResponse
    {
        abort_unless($cardLayout->school_id === auth()->user()->school_id, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:osis,perpustakaan,identitas'],
            'layout_config' => ['required', 'array'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        if ($validated['is_default'] ?? false) {
            SchoolCardLayout::forSchool()
                ->where('type', $validated['type'])
                ->where('id', '!=', $cardLayout->id)
                ->update(['is_default' => false]);
        }

        $cardLayout->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Layout kartu berhasil diupdate.']);

        return to_route('admin.card-layouts');
    }

    public function destroy(SchoolCardLayout $cardLayout): RedirectResponse
    {
        abort_unless($cardLayout->school_id === auth()->user()->school_id, 403);

        $cardLayout->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Layout kartu berhasil dihapus.']);

        return to_route('admin.card-layouts');
    }
}
