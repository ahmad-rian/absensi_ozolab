<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Enums\Religion;
use App\Enums\SchoolFeature;
use App\Jobs\RegisterStudentCardsJob;
use App\Models\CardGenerationLog;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use App\Rules\SchoolFeatureEnabled;
use App\Services\Attendance\QrTokenGenerator;
use App\Services\GoogleDriveService;
use App\Services\ParentProfileService;
use App\Services\PhotoCropService;
use App\Support\SchoolFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentRegistrationController extends Controller
{
    public function index(): Response
    {
        // Kolom `settings` ikut ditarik hanya untuk menilai fitur, lalu dibuang
        // sebelum dikirim ke halaman publik — isinya memuat template notifikasi
        // dan kredensial yang tidak boleh bocor.
        $schools = School::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'logo_path', 'settings'])
            ->filter(fn (School $school) => SchoolFeatures::for($school)->enabled(SchoolFeature::PendaftaranPublik))
            ->values();

        $classrooms = Classroom::whereIn('school_id', $schools->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'school_id', 'name', 'grade_level']);

        return Inertia::render('student-register', [
            'schools' => $schools->map(fn (School $school) => [
                'id' => $school->id,
                'name' => $school->name,
                'logo_path' => $school->logo_path,
            ]),
            'classrooms' => $classrooms,
            // Garis bantu overlay cropper diambil dari servis crop, bukan diketik ulang
            // di frontend, supaya panduan visual selalu ikut kalibrasi framing server.
            'photoGuide' => PhotoCropService::framingGuide(),
            // Mengikat endpoint pratinjau ke sesi yang benar-benar membuka
            // halaman ini, supaya tidak bisa dipanggil lepas lewat curl.
            'registrationToken' => $this->issueRegistrationToken(),
        ]);
    }

    /**
     * Token sesi pendaftaran — dipakai endpoint pratinjau foto.
     */
    private function issueRegistrationToken(): string
    {
        $token = session('registration_token');

        if (! $token) {
            $token = Str::random(40);
            session(['registration_token' => $token]);
        }

        return $token;
    }

    private function assertRegistrationToken(Request $request): void
    {
        $expected = (string) session('registration_token', '');
        $given = (string) $request->input('token', '');

        abort_if(
            $expected === '' || ! hash_equals($expected, $given),
            403,
            'Sesi pendaftaran tidak valid. Muat ulang halaman.',
        );
    }

    /**
     * Unduh foto dari Drive ke disk privat, lalu kembalikan kunci + URL
     * bertanda tangan. Path penyimpanan tidak pernah keluar ke klien.
     *
     * @return array{key: string, path: string, url: string}
     */
    private function storePreview(GoogleDriveService $service, string $driveFileId): array
    {
        $key = Str::random(32);
        $path = 'registration-previews/'.$key.'.jpg';
        $fullPath = Storage::disk('local')->path($path);

        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $service->downloadFile($driveFileId, $fullPath);

        // Berkas ini dipakai dua kali: ditampilkan di kotak crop dan jadi sumber
        // pas foto akhir. Dikecilkan ke batas yang sama dengan yang dipakai
        // PhotoCropService sebelum memotong, jadi tampilannya cepat muncul tanpa
        // mengubah hasil akhir.
        try {
            (new PhotoCropService)->normalizeForPreview($fullPath);
        } catch (\Throwable $e) {
            Log::warning('Preview normalize failed, memakai berkas asli', ['error' => $e->getMessage()]);
        }

        cache()->put('registration-preview:'.$key, $path, now()->addHour());

        return [
            'key' => $key,
            'path' => $fullPath,
            'url' => URL::temporarySignedRoute('student.register.preview-file', now()->addHour(), ['key' => $key]),
        ];
    }

    /**
     * Sajikan berkas pratinjau lewat URL bertanda tangan.
     */
    public function previewFile(string $key): StreamedResponse
    {
        $path = cache()->get('registration-preview:'.$key);

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Content-Type' => 'image/jpeg']);
    }

    public function store(Request $request, ParentProfileService $parentProfileService, QrTokenGenerator $qrGenerator): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'school_id' => ['required', 'exists:schools,id', new SchoolFeatureEnabled(SchoolFeature::PendaftaranPublik)],
            // Nama & NIS dari form publik ini berakhir di export CSV dan header
            // HTTP, jadi karakternya dibatasi di sumbernya.
            'full_name' => ['required', 'string', 'max:255', "regex:/^[\p{L}\p{N} .,'\-]+$/u"],
            'nis' => ['nullable', 'string', 'max:50', 'alpha_num', 'unique:students,nis'],
            'no_absen' => ['required', 'string', 'max:10', 'alpha_num'],
            'nisn' => ['required', 'string', 'max:20', 'alpha_num', 'unique:students,nisn'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'religion' => ['required', Rule::enum(Religion::class)],
            'classroom_id' => ['required', Rule::exists('classrooms', 'id')->where('school_id', $request->school_id)],
            'birth_place' => ['required', 'string', 'max:100'],
            'birth_date' => ['required', 'date'],
            'address' => ['required', 'string', 'max:90'],
            'parent_name' => ['required', 'string', 'max:255'],
            'parent_phone' => ['required', 'string', 'max:20'],
            'parent_email' => ['nullable', 'email', 'max:255', 'regex:/^[^\\r\\n]*$/'],
            'parent_relation' => ['required', 'string', 'in:AYAH,IBU,WALI'],
            'photo_drive_filename' => ['nullable', 'string', 'max:500'],
            'photo_key' => ['nullable', 'string', 'alpha_num', 'size:32'],
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

        // Render kartu memanggil headless Chrome. Tanpa syarat foto, endpoint
        // publik ini bisa dipakai memaksa dua job render per request.
        $generateCards = $hasPhoto && (bool) ($validated['generate_cards'] ?? false);

        if ($hasPhoto || $generateCards) {
            // Kunci ditukar jadi path di sisi server; klien tidak pernah
            // menentukan berkas mana yang dibaca lalu dihapus job.
            $previewPath = $validated['photo_key'] ?? null
                ? cache()->get('registration-preview:'.$validated['photo_key'])
                : null;

            RegisterStudentCardsJob::dispatch(
                $student->id,
                $hasPhoto ? $validated['photo_drive_filename'] : null,
                $previewPath,
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
     * Bookmarkable per-student result page (photo + generated cards).
     */
    public function result(Student $student): Response
    {
        $student->loadMissing('classroom');

        return Inertia::render('student-register-result', [
            'student' => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'nis' => $student->nis,
                'nisn' => $student->nisn,
                'classroom' => $student->classroom?->name,
                'photo_url' => $student->photo_path ? Storage::disk('public')->url($student->photo_path) : null,
            ],
            'queued' => true,
        ]);
    }

    /**
     * Preview a photo from Google Drive before registration.
     */
    public function previewPhoto(Request $request): JsonResponse
    {
        $this->assertRegistrationToken($request);

        $validated = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'filename' => ['required', 'string', 'max:500'],
        ]);

        $service = $this->driveFor($validated['school_id']);

        if (! $service) {
            return response()->json(['found' => false, 'message' => 'Google Drive belum dikonfigurasi untuk sekolah ini.']);
        }

        try {
            $files = $service['client']->findPhotoByName($validated['filename'], $service['folder']);

            if (empty($files)) {
                return response()->json(['found' => false, 'message' => 'Foto tidak ditemukan di Google Drive. Periksa lagi nama filenya.']);
            }

            $preview = $this->storePreview($service['client'], $files[0]['id']);

            return response()->json([
                'found' => true,
                'preview_url' => $preview['url'],
                'photo_key' => $preview['key'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Photo preview failed', ['error' => $e->getMessage()]);

            return response()->json(['found' => false, 'message' => 'Foto tidak dapat diambil.']);
        }
    }

    /**
     * Klien Drive + folder pencarian untuk satu sekolah, atau null bila belum
     * dikonfigurasi. Pesan galat sengaja seragam supaya tidak jadi orakel.
     *
     * @return array{client: GoogleDriveService, folder: string}|null
     */
    private function driveFor(string $schoolId): ?array
    {
        $driveConfig = School::with('driveConfig')->findOrFail($schoolId)->driveConfig;

        if (! $driveConfig || ! $driveConfig->is_active) {
            return null;
        }

        if (! GoogleDriveService::hasGlobalCredentials() && ! $driveConfig->service_account_json) {
            return null;
        }

        $folder = $driveConfig->parents_folder_id ?: $driveConfig->root_folder_id;

        if (! $folder) {
            return null;
        }

        return ['client' => GoogleDriveService::forSchool($driveConfig), 'folder' => $folder];
    }

    /**
     * Download a Drive photo to a temp file + return the auto-crop rect for the
     * drag-to-reposition UI. Keeps the temp file so the frontend can display it.
     */
    public function cropPreview(Request $request): JsonResponse
    {
        $this->assertRegistrationToken($request);

        $validated = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'filename' => ['required', 'string', 'max:500'],
        ]);

        $service = $this->driveFor($validated['school_id']);

        if (! $service) {
            return response()->json(['found' => false, 'message' => 'Google Drive belum dikonfigurasi untuk sekolah ini.']);
        }

        try {
            $files = $service['client']->findPhotoByName($validated['filename'], $service['folder']);

            if (empty($files)) {
                return response()->json(['found' => false, 'message' => 'Foto tidak ditemukan di Google Drive. Periksa lagi nama filenya.']);
            }

            $preview = $this->storePreview($service['client'], $files[0]['id']);

            return response()->json([
                'found' => true,
                'preview_url' => $preview['url'],
                // Kunci, bukan path — path dari klien tidak pernah dipercaya.
                'photo_key' => $preview['key'],
                'crop' => (new PhotoCropService)->autoCropRect($preview['path']),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Photo crop-preview failed', ['error' => $e->getMessage()]);

            return response()->json(['found' => false, 'message' => 'Foto tidak dapat diambil.']);
        }
    }
}
