<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Enums\Religion;
use App\Models\Classroom;
use App\Models\School;
use App\Models\SchoolCardLayout;
use App\Models\Student;
use App\Services\CardGeneratorService;
use App\Services\GoogleDriveService;
use App\Services\ParentProfileService;
use App\Services\PhotoCropService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentRegistrationController extends Controller
{
    public function index(): Response
    {
        $schools = School::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'logo_path']);

        $classrooms = Classroom::whereIn('school_id', $schools->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'school_id', 'name', 'grade_level']);

        return Inertia::render('student-register', [
            'schools' => $schools,
            'classrooms' => $classrooms,
        ]);
    }

    public function store(Request $request, ParentProfileService $parentProfileService): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'nis' => ['nullable', 'string', 'max:50', 'unique:students,nis'],
            'no_absen' => ['nullable', 'string', 'max:10'],
            'nisn' => ['nullable', 'string', 'max:20', 'unique:students,nisn'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'religion' => ['nullable', Rule::enum(Religion::class)],
            'classroom_id' => ['required', Rule::exists('classrooms', 'id')->where('school_id', $request->school_id)],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:500'],
            'parent_name' => ['nullable', 'string', 'max:255'],
            'parent_phone' => ['nullable', 'string', 'max:20'],
            'parent_relation' => ['nullable', 'string', 'in:AYAH,IBU,WALI'],
            'photo_drive_filename' => ['nullable', 'string', 'max:500'],
            'generate_cards' => ['nullable', 'boolean'],
        ], [
            'school_id.required' => 'Pilih sekolah terlebih dahulu.',
            'school_id.exists' => 'Sekolah tidak ditemukan.',
            'full_name.required' => 'Nama lengkap wajib diisi.',
            'nis.unique' => 'NIS sudah terdaftar. Gunakan NIS lain atau kosongkan untuk auto-generate.',
            'nisn.unique' => 'NISN sudah terdaftar. Periksa kembali NISN siswa.',
            'gender.required' => 'Pilih jenis kelamin.',
            'classroom_id.required' => 'Pilih kelas terlebih dahulu.',
            'classroom_id.exists' => 'Kelas tidak ditemukan di sekolah ini.',
            'birth_date.date' => 'Format tanggal lahir tidak valid.',
        ]);

        if (empty($validated['nis'])) {
            $validated['nis'] = now()->format('Y').str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
        }

        $qrToken = Str::random(64);
        $school = School::with('driveConfig')->findOrFail($validated['school_id']);

        $student = DB::transaction(function () use ($validated, $qrToken, $parentProfileService) {
            $student = Student::create([
                'school_id' => $validated['school_id'],
                'full_name' => $validated['full_name'],
                'nis' => $validated['nis'],
                'no_absen' => $validated['no_absen'] ?? null,
                'nisn' => $validated['nisn'] ?? null,
                'gender' => $validated['gender'],
                'religion' => $validated['religion'] ?? null,
                'classroom_id' => $validated['classroom_id'],
                'birth_place' => $validated['birth_place'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'address' => $validated['address'] ?? null,
                'parent_name' => $validated['parent_name'] ?? null,
                'parent_phone' => $validated['parent_phone'] ?? null,
                'qr_token' => $qrToken,
                'qr_issued_at' => now(),
                'is_active' => true,
            ]);

            if (! empty($validated['parent_name']) && ! empty($validated['parent_phone'])) {
                $parentProfile = $parentProfileService->findOrCreateFromRegistration(
                    $validated['school_id'],
                    $validated['parent_name'],
                    $validated['parent_phone'],
                    $validated['parent_relation'] ?? 'WALI',
                );
                $student->update(['parent_profile_id' => $parentProfile->id]);
            }

            return $student;
        });

        // Download photo from Drive if filename provided
        $photoDownloaded = false;
        if (! empty($validated['photo_drive_filename'])) {
            $photoDownloaded = $this->downloadPhotoFromDrive($student, $school, $validated['photo_drive_filename']);
        }

        // Generate cards if requested
        $generatedCards = [];
        if ($validated['generate_cards'] ?? false) {
            $generatedCards = $this->generateStudentCards($student, $school);
        }

        $student->load('classroom');

        $cardsFailed = collect($generatedCards)->where('status', 'failed')->count();

        return response()->json([
            'success' => true,
            'message' => $cardsFailed > 0
                ? "Data siswa berhasil didaftarkan! ({$cardsFailed} kartu gagal digenerate)"
                : 'Data siswa berhasil didaftarkan!',
            'student' => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'nis' => $student->nis,
                'nisn' => $student->nisn,
                'classroom' => $student->classroom?->name,
                'photo_url' => $student->photo_path
                    ? Storage::disk('public')->url($student->photo_path)
                    : null,
            ],
            'photo_downloaded' => $photoDownloaded,
            'cards' => $generatedCards,
        ]);
    }

    /**
     * Preview a photo from Google Drive before registration.
     */
    public function previewPhoto(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'filename' => ['required', 'string', 'max:500'],
        ]);

        $school = School::with('driveConfig')->findOrFail($validated['school_id']);
        $driveConfig = $school->driveConfig;

        if (! $driveConfig || ! $driveConfig->is_active) {
            return response()->json(['found' => false, 'message' => 'Google Drive belum dikonfigurasi untuk sekolah ini.']);
        }

        if (! GoogleDriveService::hasGlobalCredentials() && ! $driveConfig->service_account_json) {
            return response()->json(['found' => false, 'message' => 'Credentials Google Drive belum diset.']);
        }

        try {
            $service = GoogleDriveService::forSchool($driveConfig);
            $searchFolderId = $driveConfig->parents_folder_id ?: $driveConfig->root_folder_id ?: 'root';
            $files = $service->findFileByName($validated['filename'], $searchFolderId);

            if (empty($files)) {
                return response()->json(['found' => false, 'message' => 'File "'.$validated['filename'].'" tidak ditemukan di folder Foto Siswa.']);
            }

            // Download to temp for preview
            $driveFileId = $files[0]['id'];
            $tempName = 'temp/preview-'.Str::random(16).'.jpg';
            $fullPath = Storage::disk('public')->path($tempName);

            $dir = dirname($fullPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $service->downloadFile($driveFileId, $fullPath);

            return response()->json([
                'found' => true,
                'filename' => $files[0]['name'],
                'preview_url' => Storage::disk('public')->url($tempName),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Photo preview failed', ['filename' => $validated['filename'], 'error' => $e->getMessage()]);

            return response()->json(['found' => false, 'message' => 'Gagal mengambil foto: '.$e->getMessage()]);
        }
    }

    /**
     * Download student photo from Google Drive, smart crop to 3:4 portrait, save as WebP.
     */
    private function downloadPhotoFromDrive(Student $student, School $school, string $filename): bool
    {
        $driveConfig = $school->driveConfig;
        if (! $driveConfig || ! $driveConfig->is_active) {
            return false;
        }

        if (! GoogleDriveService::hasGlobalCredentials() && ! $driveConfig->service_account_json) {
            return false;
        }

        try {
            $service = GoogleDriveService::forSchool($driveConfig);

            $searchFolderId = $driveConfig->parents_folder_id ?: $driveConfig->root_folder_id ?: 'root';
            $files = $service->findFileByName($filename, $searchFolderId);

            if (empty($files)) {
                Log::info('Photo not found in Drive', ['filename' => $filename, 'school_id' => $school->id]);

                return false;
            }

            $driveFileId = $files[0]['id'];
            $tempPath = tempnam(sys_get_temp_dir(), 'student_photo_');
            $service->downloadFile($driveFileId, $tempPath);

            // Smart crop to 3:4 portrait + WebP
            $storagePath = sprintf('photos/students/%d/%d-%s.png', $school->id, $student->id, Str::slug($student->full_name));
            $cropService = new PhotoCropService;
            $cropService->cropAndStore($tempPath, $storagePath);

            @unlink($tempPath);

            $student->update(['photo_path' => $storagePath]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed to download photo from Drive', [
                'student_id' => $student->id,
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate OSIS + Perpustakaan cards for a student.
     *
     * @return array<int, array{type: string, url: string|null, drive_url: string|null}>
     */
    private function generateStudentCards(Student $student, School $school): array
    {
        $cards = [];
        $service = new CardGeneratorService;

        $layouts = SchoolCardLayout::where('school_id', $school->id)
            ->where('is_active', true)
            ->whereIn('type', ['osis', 'perpustakaan'])
            ->get();

        // If no layouts exist, create ATM-sized defaults (85.6x54mm)
        if ($layouts->isEmpty()) {
            $layouts = collect();
            $defaults = [
                'osis' => [
                    'name' => 'Kartu OSIS',
                    'config' => [
                        'card_width' => 813, 'card_height' => 513,
                        'header_gradient_start' => '#5dc4f5', 'header_gradient_end' => '#3aa8df',
                        'header_text_color' => '#06243a',
                        'watermark_text' => 'ORGANISASI SISWA INTRA SEKOLAH',
                        'show_emblem' => true, 'show_validity' => true,
                        'validity_text' => 'BERLAKU S/D TAMAT BELAJAR',
                        'show_qr' => true, 'show_signature' => true,
                    ],
                ],
                'perpustakaan' => [
                    'name' => 'Kartu Perpustakaan',
                    'config' => [
                        'card_width' => 813, 'card_height' => 513,
                        'header_gradient_start' => '#c9986a', 'header_gradient_end' => '#b07b4a',
                        'header_text_color' => '#1a1208',
                        'watermark_text' => 'PERPUSTAKAAN WIDYA SASTRA',
                        'show_emblem' => false, 'show_validity' => false,
                        'show_qr' => true, 'show_signature' => true,
                    ],
                ],
            ];
            foreach ($defaults as $type => $def) {
                $layouts->push(SchoolCardLayout::create([
                    'school_id' => $school->id,
                    'name' => $def['name'],
                    'type' => $type,
                    'layout_config' => $def['config'],
                    'is_default' => true,
                ]));
            }
        }

        // Resolve Drive folder for this student (shared across cards + photo)
        $studentFolderId = $this->resolveStudentDriveFolder($student, $school);

        // Upload cropped photo to Drive first
        if ($student->photo_path && $studentFolderId) {
            $this->uploadPhotoToDrive($student, $school, $studentFolderId);
        }

        foreach ($layouts as $layout) {
            try {
                $log = $service->generateAndLog($student, $layout, 'registration');

                // Upload card to student's Drive folder
                $driveUrl = null;
                if ($studentFolderId && $log->file_path) {
                    $driveUrl = $this->uploadFileToDriveFolder($log->file_path, $studentFolderId, $school, 'image/png');
                    if ($driveUrl) {
                        $log->update(['drive_url' => $driveUrl]);
                    }
                }

                $cards[] = [
                    'type' => $layout->type,
                    'name' => $layout->name,
                    'url' => $log->file_path ? Storage::disk('public')->url($log->file_path) : null,
                    'drive_url' => $driveUrl ?? $log->drive_url,
                    'status' => $log->status,
                ];
            } catch (\Throwable $e) {
                Log::warning('Card generation failed during registration', [
                    'student_id' => $student->id,
                    'layout_type' => $layout->type,
                    'error' => $e->getMessage(),
                ]);

                $cards[] = [
                    'type' => $layout->type,
                    'name' => $layout->name,
                    'url' => null,
                    'drive_url' => null,
                    'status' => 'failed',
                ];
            }
        }

        return $cards;
    }

    /**
     * Resolve or create the student's Drive folder: cards_folder / ClassName / NIS - Name
     */
    private function resolveStudentDriveFolder(Student $student, School $school): ?string
    {
        $driveConfig = $school->driveConfig;
        if (! $driveConfig || ! $driveConfig->is_active || ! $driveConfig->cards_folder_id) {
            return null;
        }

        if (! GoogleDriveService::hasGlobalCredentials() && ! $driveConfig->service_account_json) {
            return null;
        }

        try {
            $service = GoogleDriveService::forSchool($driveConfig);
            $student->loadMissing('classroom');

            $classroomName = $student->classroom?->name ?? 'Tanpa Kelas';
            $classFolderId = $service->findOrCreateFolder($classroomName, $driveConfig->cards_folder_id);

            $studentFolderName = sprintf('%s - %s', $student->nis ?? $student->id, $student->full_name);

            return $service->findOrCreateFolder($studentFolderName, $classFolderId);
        } catch (\Throwable $e) {
            Log::warning('Failed to create student Drive folder', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Upload cropped photo to student's Drive folder.
     */
    private function uploadPhotoToDrive(Student $student, School $school, string $folderId): void
    {
        try {
            $service = GoogleDriveService::forSchool($school->driveConfig);
            $fullPath = Storage::disk('public')->path($student->photo_path);
            $fileName = sprintf('foto-%s.png', Str::slug($student->full_name));

            $driveFile = $service->uploadFile($fullPath, $fileName, $folderId, 'image/png');
            $service->makePublic($driveFile->getId());
        } catch (\Throwable $e) {
            Log::warning('Photo Drive upload failed', ['student_id' => $student->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Upload a file from storage to a specific Drive folder.
     */
    private function uploadFileToDriveFolder(string $storagePath, string $folderId, School $school, string $mimeType): ?string
    {
        try {
            $service = GoogleDriveService::forSchool($school->driveConfig);
            $fullPath = Storage::disk('public')->path($storagePath);
            $fileName = basename($storagePath);

            $driveFile = $service->uploadFile($fullPath, $fileName, $folderId, $mimeType);

            return $service->makePublic($driveFile->getId());
        } catch (\Throwable $e) {
            Log::warning('File Drive upload failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
