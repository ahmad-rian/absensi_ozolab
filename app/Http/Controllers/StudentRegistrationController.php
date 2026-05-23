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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'nis' => ['nullable', 'string', 'max:50'],
            'no_absen' => ['nullable', 'string', 'max:10'],
            'nisn' => ['nullable', 'string', 'max:20'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'religion' => ['nullable', Rule::enum(Religion::class)],
            'classroom_id' => ['required', Rule::exists('classrooms', 'id')->where('school_id', $request->school_id)],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:500'],
            'parent_name' => ['nullable', 'string', 'max:255'],
            'parent_phone' => ['nullable', 'string', 'max:20'],
            'photo_drive_filename' => ['nullable', 'string', 'max:500'],
            'generate_cards' => ['nullable', 'boolean'],
        ]);

        if (empty($validated['nis'])) {
            $validated['nis'] = now()->format('Y').str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
        }

        $qrToken = Str::random(64);
        $school = School::with('driveConfig')->findOrFail($validated['school_id']);

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

        if ($request->wantsJson() || $request->boolean('generate_cards')) {
            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil didaftarkan!',
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

        return redirect()
            ->route('student.register')
            ->with('success', 'Data siswa berhasil didaftarkan! Terima kasih telah mengisi formulir pendaftaran.');
    }

    /**
     * Download student photo from Google Drive parents folder by filename.
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

            // Search for file in parents folder
            $searchFolderId = $driveConfig->parents_folder_id ?: $driveConfig->root_folder_id ?: 'root';
            $files = $service->findFileByName($filename, $searchFolderId);

            if (empty($files)) {
                Log::info('Photo not found in Drive', ['filename' => $filename, 'school_id' => $school->id]);

                return false;
            }

            $driveFileId = $files[0]['id'];
            $tempPath = tempnam(sys_get_temp_dir(), 'student_photo_');

            $service->downloadFile($driveFileId, $tempPath);

            // Convert to WebP and store
            $dir = sprintf('photos/students/%d', $school->id);
            Storage::disk('public')->makeDirectory($dir);

            $outputFilename = sprintf('%s/%d-%s.webp', $dir, $student->id, Str::slug($student->full_name));
            $outputPath = Storage::disk('public')->path($outputFilename);

            $this->convertToWebp($tempPath, $outputPath);

            @unlink($tempPath);

            $student->update(['photo_path' => $outputFilename]);

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
     * Convert image to WebP using GD (handles large Canon photos 5-10MB).
     */
    private function convertToWebp(string $inputPath, string $outputPath, int $quality = 80, int $maxWidth = 800): void
    {
        $info = getimagesize($inputPath);
        if (! $info) {
            throw new \RuntimeException('Cannot read image file.');
        }

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($inputPath),
            IMAGETYPE_PNG => imagecreatefrompng($inputPath),
            IMAGETYPE_WEBP => imagecreatefromwebp($inputPath),
            IMAGETYPE_GIF => imagecreatefromgif($inputPath),
            default => throw new \RuntimeException('Unsupported image format.'),
        };

        $origWidth = imagesx($image);
        $origHeight = imagesy($image);

        if ($origWidth > $maxWidth) {
            $ratio = $maxWidth / $origWidth;
            $newWidth = $maxWidth;
            $newHeight = (int) ($origHeight * $ratio);

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
            imagedestroy($image);
            $image = $resized;
        }

        imagewebp($image, $outputPath, $quality);
        imagedestroy($image);
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

        // If no layouts exist, create defaults
        if ($layouts->isEmpty()) {
            $layouts = collect();
            foreach (['osis', 'perpustakaan'] as $type) {
                $layouts->push(SchoolCardLayout::create([
                    'school_id' => $school->id,
                    'name' => $type === 'osis' ? 'Kartu OSIS' : 'Kartu Perpustakaan',
                    'type' => $type,
                    'layout_config' => ['card_width' => 638, 'card_height' => 1011, 'show_qr' => true],
                    'is_default' => true,
                ]));
            }
        }

        foreach ($layouts as $layout) {
            try {
                $log = $service->generateAndLog($student, $layout, 'registration');

                // Upload to Drive with folder structure: cards_folder/ClassName/StudentName/
                $driveUrl = $this->uploadCardToDrive($log, $student, $school);

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
     * Upload card to Drive with folder structure: cards_folder / ClassName / StudentName /
     */
    private function uploadCardToDrive($log, Student $student, School $school): ?string
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

            // Create class folder
            $classroomName = $student->classroom?->name ?? 'Tanpa Kelas';
            $classFolderId = $service->findOrCreateFolder($classroomName, $driveConfig->cards_folder_id);

            // Create student folder
            $studentFolderName = sprintf('%s - %s', $student->nis ?? $student->id, $student->full_name);
            $studentFolderId = $service->findOrCreateFolder($studentFolderName, $classFolderId);

            // Upload card
            $fullPath = Storage::disk('public')->path($log->file_path);
            $fileName = basename($log->file_path);
            $driveFile = $service->uploadFile($fullPath, $fileName, $studentFolderId, 'image/png');
            $driveUrl = $service->makePublic($driveFile->getId());

            $log->update([
                'drive_file_id' => $driveFile->getId(),
                'drive_url' => $driveUrl,
            ]);

            return $driveUrl;
        } catch (\Throwable $e) {
            Log::warning('Card Drive upload failed', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
