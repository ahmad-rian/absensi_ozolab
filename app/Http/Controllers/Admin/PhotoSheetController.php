<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CardGenerationLog;
use App\Models\School;
use App\Models\Student;
use App\Services\GoogleDriveService;
use App\Services\PhotoSheetGeneratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PhotoSheetController extends Controller
{
    public function generate(Request $request, Student $siswa, PhotoSheetGeneratorService $service): RedirectResponse
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

        try {
            $result = $service->generate($siswa, $validated['template'], $validated['caption'] ?? '');
            $path = $result['path'];

            $school = School::with('driveConfig')->find($siswa->school_id);
            $driveConfig = $school?->driveConfig;

            if ($driveConfig && $driveConfig->is_active) {
                $drive = GoogleDriveService::forSchool($driveConfig);
                $drive->ensureSubfolders();

                $fullPath = Storage::disk('public')->path($path);
                $driveFile = $drive->uploadFile($fullPath, basename($path), $driveConfig->sheets_folder_id, 'image/png');
                $driveUrl = $drive->makePublic($driveFile->getId());

                // Drive-only storage: remove local file after upload
                Storage::disk('public')->delete($path);

                $log->update([
                    'status' => 'completed',
                    'file_path' => null,
                    'drive_file_id' => $driveFile->getId(),
                    'drive_url' => $driveUrl,
                ]);
            } else {
                // Fallback: keep local file so the sheet isn't lost
                $log->update([
                    'status' => 'completed',
                    'file_path' => $path,
                    'drive_url' => null,
                ]);
            }

            Inertia::flash('toast', ['type' => 'success', 'message' => 'Pas foto berhasil dibuat.']);
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 500),
            ]);

            Inertia::flash('toast', ['type' => 'error', 'message' => 'Gagal membuat pas foto.']);
        }

        return back();
    }
}
