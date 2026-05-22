<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolAlbumLayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlbumLayoutController extends Controller
{
    public function index(): Response
    {
        $layouts = SchoolAlbumLayout::forSchool()
            ->orderBy('name')
            ->get()
            ->map(fn (SchoolAlbumLayout $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'paper_size' => $l->paper_size,
                'orientation' => $l->orientation,
                'columns' => $l->columns,
                'rows' => $l->rows,
                'is_default' => $l->is_default,
                'is_active' => $l->is_active,
                'layout_config' => $l->layout_config,
            ]);

        return Inertia::render('admin/album-layouts/index', [
            'layouts' => $layouts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'paper_size' => ['required', 'in:A4,A3,Letter'],
            'orientation' => ['required', 'in:portrait,landscape'],
            'columns' => ['required', 'integer', 'min:1', 'max:10'],
            'rows' => ['required', 'integer', 'min:1', 'max:10'],
            'layout_config' => ['required', 'array'],
            'is_default' => ['boolean'],
        ]);

        if ($validated['is_default'] ?? false) {
            SchoolAlbumLayout::forSchool()->update(['is_default' => false]);
        }

        SchoolAlbumLayout::create($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Layout album berhasil disimpan.']);

        return to_route('admin.album-layouts');
    }

    public function update(Request $request, SchoolAlbumLayout $albumLayout): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'paper_size' => ['required', 'in:A4,A3,Letter'],
            'orientation' => ['required', 'in:portrait,landscape'],
            'columns' => ['required', 'integer', 'min:1', 'max:10'],
            'rows' => ['required', 'integer', 'min:1', 'max:10'],
            'layout_config' => ['required', 'array'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        if ($validated['is_default'] ?? false) {
            SchoolAlbumLayout::forSchool()
                ->where('id', '!=', $albumLayout->id)
                ->update(['is_default' => false]);
        }

        $albumLayout->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Layout album berhasil diupdate.']);

        return to_route('admin.album-layouts');
    }

    public function destroy(SchoolAlbumLayout $albumLayout): RedirectResponse
    {
        $albumLayout->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Layout album berhasil dihapus.']);

        return to_route('admin.album-layouts');
    }
}
