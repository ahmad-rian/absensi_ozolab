<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GeneratePhotoSheetJob;
use App\Models\CardGenerationLog;
use App\Models\Student;
use App\Services\PhotoSheetGeneratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PhotoSheetController extends Controller
{
    public function generate(Request $request, Student $siswa): RedirectResponse
    {
        $validated = $request->validate([
            'template' => ['required', Rule::in(array_keys(PhotoSheetGeneratorService::TEMPLATES))],
            'caption' => ['nullable', 'string', 'max:255'],
        ], [
            'template.required' => 'Template wajib dipilih.',
            'template.in' => 'Template tidak valid.',
        ]);

        $log = CardGenerationLog::create([
            'school_id' => $siswa->school_id,
            'student_id' => $siswa->id,
            'type' => 'photo_sheet',
            'status' => 'processing',
            'generated_by' => 'admin',
        ]);

        GeneratePhotoSheetJob::dispatch($log->id, $siswa->id, $validated['template'], $validated['caption'] ?? '');

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pas foto sedang diproses. Hasil muncul otomatis.']);

        return back();
    }
}
