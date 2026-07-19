<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Enums\Religion;
use App\Jobs\RegisterStudentCardsJob;
use App\Models\CardGenerationLog;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use App\Services\Attendance\QrTokenGenerator;
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

    public function store(Request $request, ParentProfileService $parentProfileService, QrTokenGenerator $qrGenerator): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'nis' => ['nullable', 'string', 'max:50', 'unique:students,nis'],
            'no_absen' => ['required', 'string', 'max:10'],
            'nisn' => ['required', 'string', 'max:20', 'unique:students,nisn'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'religion' => ['required', Rule::enum(Religion::class)],
            'classroom_id' => ['required', Rule::exists('classrooms', 'id')->where('school_id', $request->school_id)],
            'birth_place' => ['required', 'string', 'max:100'],
            'birth_date' => ['required', 'date'],
            'address' => ['required', 'string', 'max:500'],
            'parent_name' => ['required', 'string', 'max:255'],
            'parent_phone' => ['required', 'string', 'max:20'],
            'parent_email' => ['nullable', 'email', 'max:255'],
            'parent_relation' => ['required', 'string', 'in:AYAH,IBU,WALI'],
            'photo_drive_filename' => ['nullable', 'string', 'max:500'],
            'photo_temp' => ['nullable', 'string', 'max:255'],
            'manual_crop' => ['nullable', 'array'],
            'manual_crop.sx' => ['required_with:manual_crop', 'numeric', 'between:0,1'],
            'manual_crop.sy' => ['required_with:manual_crop', 'numeric', 'between:0,1'],
            'manual_crop.sw' => ['required_with:manual_crop', 'numeric', 'between:0,1'],
            'manual_crop.sh' => ['required_with:manual_crop', 'numeric', 'between:0,1'],
            'generate_cards' => ['nullable', 'boolean'],
        ], [
            'school_id.required' => 'Pilih sekolah terlebih dahulu.',
            'school_id.exists' => 'Sekolah tidak ditemukan.',
            'full_name.required' => 'Nama lengkap wajib diisi.',
            'nis.unique' => 'NIS sudah terdaftar. Gunakan NIS lain atau kosongkan untuk auto-generate.',
            'no_absen.required' => 'No. absen wajib diisi.',
            'nisn.required' => 'NISN wajib diisi.',
            'nisn.unique' => 'NISN sudah terdaftar. Periksa kembali NISN siswa.',
            'gender.required' => 'Pilih jenis kelamin.',
            'religion.required' => 'Pilih agama.',
            'classroom_id.required' => 'Pilih kelas terlebih dahulu.',
            'classroom_id.exists' => 'Kelas tidak ditemukan di sekolah ini.',
            'birth_place.required' => 'Tempat lahir wajib diisi.',
            'birth_date.required' => 'Tanggal lahir wajib diisi.',
            'birth_date.date' => 'Format tanggal lahir tidak valid.',
            'address.required' => 'Alamat wajib diisi.',
            'parent_name.required' => 'Nama orang tua wajib diisi.',
            'parent_phone.required' => 'No. WhatsApp orang tua wajib diisi.',
            'parent_email.email' => 'Format email orang tua tidak valid.',
            'parent_relation.required' => 'Pilih hubungan orang tua.',
        ]);

        if (empty($validated['nis'])) {
            $validated['nis'] = now()->format('Y').str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
        }

        $school = School::with('driveConfig')->findOrFail($validated['school_id']);

        $student = DB::transaction(function () use ($validated, $parentProfileService, $qrGenerator) {
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
                'is_active' => true,
            ]);

            // Token QR berbasis NISN + signature HMAC (lihat QrTokenGenerator).
            $qrGenerator->generate($student);

            if (! empty($validated['parent_name']) && ! empty($validated['parent_phone'])) {
                $parentProfile = $parentProfileService->findOrCreateFromRegistration(
                    $validated['school_id'],
                    $validated['parent_name'],
                    $validated['parent_phone'],
                    $validated['parent_relation'] ?? 'WALI',
                    $validated['parent_email'] ?? null,
                );
                $student->update(['parent_profile_id' => $parentProfile->id]);
            }

            return $student;
        });

        // Offload the slow work (Drive photo download + crop + card render) to the
        // queue so the request returns instantly and never hits a gateway timeout.
        $hasPhoto = ! empty($validated['photo_drive_filename']);
        $generateCards = (bool) ($validated['generate_cards'] ?? false);

        if ($hasPhoto || $generateCards) {
            RegisterStudentCardsJob::dispatch(
                $student->id,
                $hasPhoto ? $validated['photo_drive_filename'] : null,
                $validated['photo_temp'] ?? null,
                $validated['manual_crop'] ?? null,
                $generateCards,
            );
        }

        $student->load('classroom');

        return response()->json([
            'success' => true,
            'message' => ($hasPhoto || $generateCards)
                ? 'Data siswa berhasil didaftarkan! Foto & kartu sedang diproses dan akan tersimpan ke Google Drive.'
                : 'Data siswa berhasil didaftarkan!',
            'queued' => $hasPhoto || $generateCards,
            'student' => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'nis' => $student->nis,
                'nisn' => $student->nisn,
                'classroom' => $student->classroom?->name,
                'photo_url' => null,
            ],
        ]);
    }

    /**
     * Poll the async registration outputs (photo, cards, pas-foto sheet).
     */
    public function status(Student $student): JsonResponse
    {
        $labels = ['photo' => 'Foto Siswa', 'photo_sheet' => 'Lembar Pas Foto (4R)', 'card' => 'Kartu'];

        $items = CardGenerationLog::where('student_id', $student->id)
            ->where('generated_by', 'registration')
            ->with('cardLayout:id,name')
            ->latest()
            ->get()
            ->map(fn (CardGenerationLog $log) => [
                'type' => $log->type,
                'name' => $log->type === 'card' ? ($log->cardLayout?->name ?? 'Kartu') : ($labels[$log->type] ?? $log->type),
                'status' => $log->status,
                'url' => $log->drive_url ?: ($log->file_path ? Storage::disk('public')->url($log->file_path) : null),
                'thumb_url' => $log->file_path ? Storage::disk('public')->url($log->file_path) : null,
            ])
            ->values();

        $pending = $items->contains(fn ($i) => in_array($i['status'], ['processing', 'pending'], true));

        return response()->json([
            'done' => $items->isNotEmpty() && ! $pending,
            'items' => $items,
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
     * Download a Drive photo to a temp file + return the auto-crop rect for the
     * drag-to-reposition UI. Keeps the temp file so the frontend can display it.
     */
    public function cropPreview(Request $request): JsonResponse
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

            $driveFileId = $files[0]['id'];
            $tempName = 'temp/preview-'.Str::random(16).'.jpg';
            $fullPath = Storage::disk('public')->path($tempName);

            $dir = dirname($fullPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $service->downloadFile($driveFileId, $fullPath);

            $cropService = new PhotoCropService;

            return response()->json([
                'found' => true,
                'filename' => $files[0]['name'],
                'preview_url' => Storage::disk('public')->url($tempName),
                'photo_temp' => $tempName, // reuse on submit → no second Drive download
                'crop' => $cropService->autoCropRect($fullPath),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Photo crop-preview failed', ['filename' => $validated['filename'], 'error' => $e->getMessage()]);

            return response()->json(['found' => false, 'message' => 'Gagal mengambil foto: '.$e->getMessage()]);
        }
    }
}
