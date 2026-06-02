<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolCardLayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SchoolController extends Controller
{
    public function index(Request $request): Response
    {
        $schools = School::withCount(['users', 'students', 'classrooms'])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/schools/index', [
            'schools' => $schools,
            'filters' => ['search' => $request->search ?? ''],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/schools/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_active'] = true;
        $validated['settings'] = [
            'school_name' => $validated['name'],
            'default_check_in_time' => '07:00',
            'late_threshold_time' => '07:15',
            'default_check_out_time' => '14:30',
            'timezone' => 'Asia/Jakarta',
            'whatsapp_enabled' => true,
            'notify_on_check_in' => true,
            'notify_on_check_out' => false,
        ];

        // Ensure unique slug
        $baseSlug = $validated['slug'];
        $i = 1;
        while (School::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $baseSlug.'-'.$i++;
        }

        $school = School::create($validated);

        $this->createDefaultCardLayouts($school);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sekolah berhasil ditambahkan.']);

        return to_route('admin.schools.index');
    }

    public function edit(School $school): Response
    {
        return Inertia::render('admin/schools/edit', [
            'school' => $school,
        ]);
    }

    public function update(Request $request, School $school): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $school->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sekolah berhasil diperbarui.']);

        return to_route('admin.schools.index');
    }

    public function destroy(School $school): RedirectResponse
    {
        if ($school->students()->count() > 0) {
            return back()->withErrors(['delete' => 'Sekolah masih memiliki siswa terdaftar.']);
        }

        $school->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sekolah berhasil dihapus.']);

        return to_route('admin.schools.index');
    }

    /**
     * Create default card layouts for a newly created school.
     */
    private function createDefaultCardLayouts(School $school): void
    {
        SchoolCardLayout::create([
            'school_id' => $school->id,
            'name' => 'Kartu OSIS Default',
            'type' => 'osis',
            'is_default' => true,
            'is_active' => true,
            'layout_config' => [
                'card_width' => 813,
                'card_height' => 513,
                'header_gradient_start' => '#5dc4f5',
                'header_gradient_end' => '#3aa8df',
                'header_text_color' => '#06243a',
                'watermark_text' => 'ORGANISASI SISWA INTRA SEKOLAH',
                'show_emblem' => true,
                'show_validity' => true,
                'validity_text' => 'BERLAKU S/D TAMAT BELAJAR',
                'photo_width_mm' => 16,
                'photo_height_mm' => 21,
                'qr_size_mm' => 15,
                'show_qr' => true,
                'show_signature' => true,
                'font_family' => 'Manrope',
                'font_school' => 16,
                'font_field' => 15,
                'frame_id' => null,
            ],
        ]);

        SchoolCardLayout::create([
            'school_id' => $school->id,
            'name' => 'Kartu Perpustakaan Default',
            'type' => 'perpustakaan',
            'is_default' => true,
            'is_active' => true,
            'layout_config' => [
                'card_width' => 813,
                'card_height' => 513,
                'header_gradient_start' => '#c9986a',
                'header_gradient_end' => '#b07b4a',
                'header_text_color' => '#1a1208',
                'watermark_text' => 'PERPUSTAKAAN SEKOLAH',
                'show_emblem' => false,
                'show_validity' => false,
                'validity_text' => 'BERLAKU S/D TAMAT BELAJAR',
                'photo_width_mm' => 16,
                'photo_height_mm' => 21,
                'qr_size_mm' => 15,
                'show_qr' => true,
                'show_signature' => true,
                'font_family' => 'Manrope',
                'font_school' => 16,
                'font_field' => 15,
                'frame_id' => null,
            ],
        ]);
    }
}
