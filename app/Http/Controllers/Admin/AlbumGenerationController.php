<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\School;
use App\Models\SchoolAlbumLayout;
use App\Models\Student;
use App\Services\AlbumGeneratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AlbumGenerationController extends Controller
{
    public function index(): Response
    {
        $layouts = SchoolAlbumLayout::forSchool()
            ->where('is_active', true)
            ->get(['id', 'name', 'paper_size', 'orientation', 'columns', 'rows']);

        $classrooms = Classroom::forSchool()
            ->orderBy('name')
            ->get(['id', 'name']);

        $school = School::with('driveConfig')->find(auth()->user()->school_id);
        $driveConfig = $school?->driveConfig;

        return Inertia::render('admin/album-generation/index', [
            'layouts' => $layouts,
            'classrooms' => $classrooms,
            'driveConfigured' => $driveConfig && $driveConfig->is_active && $driveConfig->service_account_json,
        ]);
    }

    public function generate(Request $request, AlbumGeneratorService $service): BinaryFileResponse|RedirectResponse
    {
        $validated = $request->validate([
            'layout_id' => ['required', 'exists:school_album_layouts,id'],
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
        ]);

        $layout = SchoolAlbumLayout::findOrFail($validated['layout_id']);
        $school = School::findOrFail(auth()->user()->school_id);

        $students = Student::forSchool()
            ->with('classroom')
            ->when($validated['classroom_id'] ?? null, fn ($q, $id) => $q->where('classroom_id', $id))
            ->orderBy('full_name')
            ->get();

        if ($students->isEmpty()) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Tidak ada siswa yang ditemukan.']);

            return to_route('admin.album-generation');
        }

        $pages = $service->generateAlbum($students, $layout, $school);

        if (count($pages) === 1) {
            return response()->download(
                Storage::disk('public')->path($pages[0]['path']),
                'album-'.$school->slug.'.png',
            );
        }

        // Multiple pages — create ZIP
        $zipName = sprintf('albums/%d/album-%s.zip', $school->id, now()->format('Ymd-His'));
        $zipPath = Storage::disk('public')->path($zipName);
        $dir = dirname($zipPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE);
        foreach ($pages as $page) {
            $zip->addFile(
                Storage::disk('public')->path($page['path']),
                basename($page['path']),
            );
        }
        $zip->close();

        return response()->download($zipPath, 'album-'.$school->slug.'.zip');
    }
}
