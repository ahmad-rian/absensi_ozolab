<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrayerType;
use App\Enums\SchoolFeature;
use App\Http\Controllers\Controller;
use App\Models\CardGenerationLog;
use App\Models\Classroom;
use App\Models\ParentProfile;
use App\Models\Student;
use App\Services\Attendance\QrTokenGenerator;
use App\Services\PhotoSheetGeneratorService;
use App\Services\Student\StudentDrivePhotoLocator;
use App\Services\Student\StudentStatsBuilder;
use App\Support\SchoolFeatures;
use App\Support\SchoolTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SiswaController extends Controller
{
    public function index(Request $request): Response
    {
        $students = Student::forSchool()
            ->with(['classroom', 'parentProfile.user'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%");
                });
            })
            ->when($request->classroom_id, function ($query, $classroomId) {
                $query->where('classroom_id', $classroomId);
            })
            ->orderBy('full_name')
            ->paginate(20)
            ->withQueryString();

        $classrooms = Classroom::forSchool()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('admin/siswa/index', [
            'students' => $students,
            'classrooms' => $classrooms,
            'filters' => [
                'search' => $request->search ?? '',
                'classroom_id' => $request->classroom_id ?? '',
            ],
        ]);
    }

    public function show(Request $request, Student $siswa, QrTokenGenerator $qrGenerator, StudentStatsBuilder $stats, StudentDrivePhotoLocator $locator): Response
    {
        $siswa->load(['classroom', 'parentProfile.user']);

        $qrSvg = $qrGenerator->renderSvg($siswa);

        $studentData = $siswa->toArray();
        if (isset($studentData['parent_profile'])) {
            $studentData['parent_profile']['relation_label'] = $siswa->parentProfile?->relation?->label();
        }

        $photoSheets = CardGenerationLog::where('student_id', $siswa->id)
            ->where('type', 'photo_sheet')
            ->latest()
            ->take(10)
            ->get()
            ->map(fn (CardGenerationLog $log) => [
                'id' => $log->id,
                'status' => $log->status,
                'file_url' => $log->file_path ? Storage::disk('public')->url($log->file_path) : null,
                'drive_url' => $log->drive_url,
                'created_at' => SchoolTime::display($log->created_at),
            ]);

        $photoSheetTemplates = collect(PhotoSheetGeneratorService::TEMPLATES)
            ->map(fn (array $config, string $key) => ['value' => $key, 'label' => $config['label']])
            ->values();

        $range = $stats->resolveRange($request->query('start_date'), $request->query('end_date'));

        return Inertia::render('admin/siswa/show', [
            'student' => array_merge($studentData, [
                'religion_label' => $siswa->religion?->label(),
                'photo_url' => $siswa->photo_path
                    ? Storage::disk('public')->url($siswa->photo_path)
                    : null,
            ]),
            'qrSvg' => $qrSvg,
            'photoStatus' => $this->photoStatus($siswa),
            'photoSheets' => $photoSheets,
            'photoSheetTemplates' => $photoSheetTemplates,
            'cards' => $this->generatedCards($siswa),
            // Kartu yang masih dirender tidak muncul di `cards` — daftar itu hanya
            // berisi yang sudah selesai supaya tautan lama tetap bisa dibuka
            // selama yang baru dibuat. Tanpa penanda ini tombol "generate ulang"
            // tidak memberi tanda apa pun bahwa ia berhasil ditekan.
            'cardsProcessing' => CardGenerationLog::where('student_id', $siswa->id)
                ->where('type', 'card')
                ->where('status', 'processing')
                ->exists(),
            'filters' => [
                ...$range,
                'label' => $stats->rangeLabel($range['start'], $range['end']),
            ],
            // Ditunda supaya tab Profil tampil seketika; chart menyusul.
            'attendance' => Inertia::defer(fn () => $stats->attendanceFor($siswa, $range['start'], $range['end'])),
            // Statistik sholat hanya dihitung saat tabnya dibuka. Menghitung
            // dua jenis sholat di setiap kunjungan halaman profil terlalu mahal
            // untuk halaman yang paling sering dipakai sekadar mencetak QR.
            'prayerDhuha' => Inertia::optional(
                fn () => $stats->prayerFor($siswa, $range['start'], $range['end'], PrayerType::Dhuha),
            ),
            'prayerDzuhur' => Inertia::optional(
                fn () => $stats->prayerFor($siswa, $range['start'], $range['end'], PrayerType::Dzuhur),
            ),
            // Menembak API Drive dua kali, jadi baru dijalankan ketika operator
            // benar-benar menekan tombolnya — bukan di setiap kunjungan halaman.
            'drivePhoto' => Inertia::optional(fn () => $this->drivePhotoPayload($siswa, $locator)),
        ]);
    }

    /**
     * Keadaan pas foto siswa di Drive, untuk penanda di halaman detail.
     *
     * Dibaca dari riwayat generate, bukan dari Drive — memukul API Drive di
     * setiap kunjungan halaman terlalu mahal, dan itu sebabnya `drivePhoto`
     * dibuat opsional. Yang menentukan `berhasil` adalah `drive_file_id`, bukan
     * status log: unggahan yang gagal dulu tetap tercatat `completed` dan tidak
     * terlihat siapa pun sampai ada yang membuka foldernya di Drive.
     *
     * @return array<string, mixed>|null
     */
    private function photoStatus(Student $siswa): ?array
    {
        $log = CardGenerationLog::where('student_id', $siswa->id)
            ->where('type', 'photo')
            ->latest()
            ->first();

        if (! $log) {
            return null;
        }

        return [
            'status' => $log->status,
            'uploaded' => $log->drive_file_id !== null,
            'drive_url' => $log->drive_url,
            'created_at' => SchoolTime::display($log->created_at),
        ];
    }

    /**
     * Tautan berkas pas foto siswa di Google Drive.
     *
     * `found` dipisah dari isinya supaya frontend bisa membedakan "belum dicari"
     * (prop belum ada) dari "sudah dicari, tidak ketemu".
     *
     * @return array<string, mixed>
     */
    private function drivePhotoPayload(Student $siswa, StudentDrivePhotoLocator $locator): array
    {
        $enabled = SchoolFeatures::for($siswa->school)->enabled(SchoolFeature::IntegrasiDrive);

        return [
            'feature_enabled' => $enabled,
            'file' => $enabled ? $locator->locate($siswa) : null,
            'expected_file_name' => StudentDrivePhotoLocator::expectedFileName($siswa),
            'expected_folder' => StudentDrivePhotoLocator::expectedFolderName($siswa),
        ];
    }

    /**
     * Cari ulang pas foto di Drive setelah operator menaruhnya di sana — tanpa ini
     * hasil "tidak ketemu" bertahan sampai cache-nya kedaluwarsa.
     */
    public function refreshDrivePhoto(Student $siswa, StudentDrivePhotoLocator $locator): RedirectResponse
    {
        $locator->forget($siswa);

        // Bukan back(): rute ini hanya menerima POST, jadi mengalihkan ke Referer
        // berisiko mendarat di URL tanpa rute GET dan menghasilkan 404.
        return to_route('admin.siswa.show', $siswa);
    }

    /**
     * Kepesertaan absen sholat khusus siswa ini.
     *
     * `null` mengembalikannya ke aturan sekolah.
     */
    public function updatePrayerOptIn(Request $request, Student $siswa): RedirectResponse
    {
        $validated = $request->validate([
            'prayer_opt_in' => ['present', 'nullable', 'boolean'],
        ]);

        $siswa->update(['prayer_opt_in' => $validated['prayer_opt_in']]);

        return back();
    }

    /**
     * Kartu terbaru per jenis layout (OSIS / Perpustakaan / Identitas),
     * lengkap dengan tautan langsung ke berkasnya di Google Drive.
     *
     * @return array<int, array<string, mixed>>
     */
    private function generatedCards(Student $siswa): array
    {
        return CardGenerationLog::where('student_id', $siswa->id)
            ->where('type', 'card')
            ->where('status', 'completed')
            ->with('cardLayout:id,name,type')
            ->latest()
            ->get()
            ->filter(fn (CardGenerationLog $log) => $log->cardLayout !== null)
            ->unique(fn (CardGenerationLog $log) => $log->cardLayout->type)
            ->values()
            ->map(fn (CardGenerationLog $log) => [
                'id' => $log->id,
                'layout_type' => $log->cardLayout->type,
                'layout_name' => $log->cardLayout->name,
                'drive_url' => $log->drive_url,
                'file_url' => $log->file_path ? Storage::disk('public')->url($log->file_path) : null,
                'created_at' => SchoolTime::display($log->created_at),
            ])
            ->all();
    }

    public function qrCode(Student $siswa, QrTokenGenerator $qrGenerator): HttpResponse
    {
        $svg = $qrGenerator->renderSvg($siswa);
        // `nis` ikut di-slug: nilainya berasal dari pendaftaran publik dan bisa
        // menyuntik parameter `filename*` ke header Content-Disposition.
        $filename = 'qr-'.Str::slug($siswa->nis.'-'.$siswa->full_name).'.svg';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function create(): Response
    {
        $classrooms = Classroom::forSchool()->orderBy('name')->get(['id', 'name']);
        $parentProfiles = ParentProfile::forSchool()
            ->with('user:id,name')
            ->get(['id', 'user_id', 'school_id']);

        return Inertia::render('admin/siswa/create', [
            'classrooms' => $classrooms,
            'parentProfiles' => $parentProfiles,
        ]);
    }

    public function store(Request $request, QrTokenGenerator $qrGenerator): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'nis' => ['nullable', 'string', 'max:50', Rule::unique('students', 'nis')->where('school_id', auth()->user()->school_id)->whereNull('deleted_at')],
            'no_absen' => ['nullable', 'string', 'max:20'],
            'nisn' => ['nullable', 'string', 'max:50', Rule::unique('students', 'nisn')->where('school_id', auth()->user()->school_id)->whereNull('deleted_at')],
            'gender' => ['required', 'in:LAKI_LAKI,PEREMPUAN'],
            'religion' => ['nullable', 'in:ISLAM,KRISTEN,KATOLIK,HINDU,BUDDHA,KONGHUCU'],
            'classroom_id' => ['required', $this->belongsToSchool('classrooms')],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:90'],
            'parent_profile_id' => ['nullable', $this->belongsToSchool('parent_profiles')],
        ], [
            'full_name.required' => 'Nama lengkap wajib diisi.',
            'full_name.max' => 'Nama lengkap maksimal 255 karakter.',
            'nis.unique' => 'NIS sudah digunakan.',
            'nis.max' => 'NIS maksimal 50 karakter.',
            'no_absen.max' => 'No. Absen maksimal 20 karakter.',
            'nisn.unique' => 'NISN sudah digunakan.',
            'nisn.max' => 'NISN maksimal 50 karakter.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'gender.in' => 'Jenis kelamin tidak valid.',
            'classroom_id.required' => 'Kelas wajib dipilih.',
            'classroom_id.exists' => 'Kelas tidak ditemukan.',
            'birth_date.date' => 'Format tanggal lahir tidak valid.',
            'parent_profile_id.exists' => 'Data orang tua tidak ditemukan.',
        ]);

        // school_id diisi otomatis oleh trait BelongsToSchool
        $student = Student::create($validated);

        // Token QR dibangun dari NISN + signature HMAC (lihat QrTokenGenerator)
        $qrGenerator->generate($student);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data siswa berhasil ditambahkan.']);

        return to_route('admin.siswa.index');
    }

    public function edit(Student $siswa): Response
    {
        $classrooms = Classroom::forSchool()->orderBy('name')->get(['id', 'name']);
        $parentProfiles = ParentProfile::forSchool()
            ->with('user:id,name')
            ->get(['id', 'user_id', 'school_id']);

        return Inertia::render('admin/siswa/edit', [
            'student' => $siswa->load(['classroom', 'parentProfile.user']),
            'classrooms' => $classrooms,
            'parentProfiles' => $parentProfiles,
        ]);
    }

    public function update(Request $request, Student $siswa): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'nis' => ['nullable', 'string', 'max:50', Rule::unique('students', 'nis')->where('school_id', auth()->user()->school_id)->whereNull('deleted_at')->ignore($siswa->id)],
            'no_absen' => ['nullable', 'string', 'max:20'],
            'nisn' => ['nullable', 'string', 'max:50', Rule::unique('students', 'nisn')->where('school_id', auth()->user()->school_id)->whereNull('deleted_at')->ignore($siswa->id)],
            'gender' => ['required', 'in:LAKI_LAKI,PEREMPUAN'],
            'religion' => ['nullable', 'in:ISLAM,KRISTEN,KATOLIK,HINDU,BUDDHA,KONGHUCU'],
            'classroom_id' => ['required', $this->belongsToSchool('classrooms')],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:90'],
            'parent_profile_id' => ['nullable', $this->belongsToSchool('parent_profiles')],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'full_name.required' => 'Nama lengkap wajib diisi.',
            'full_name.max' => 'Nama lengkap maksimal 255 karakter.',
            'nis.unique' => 'NIS sudah digunakan.',
            'nis.max' => 'NIS maksimal 50 karakter.',
            'no_absen.max' => 'No. Absen maksimal 20 karakter.',
            'nisn.unique' => 'NISN sudah digunakan.',
            'nisn.max' => 'NISN maksimal 50 karakter.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'gender.in' => 'Jenis kelamin tidak valid.',
            'classroom_id.required' => 'Kelas wajib dipilih.',
            'classroom_id.exists' => 'Kelas tidak ditemukan.',
            'birth_date.date' => 'Format tanggal lahir tidak valid.',
            'parent_profile_id.exists' => 'Data orang tua tidak ditemukan.',
        ]);

        $siswa->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data siswa berhasil diperbarui.']);

        // Ke halaman detail, bukan kembali ke daftar: operator baru saja mengubah
        // satu siswa dan hal pertama yang ingin dilakukannya adalah memeriksa
        // hasilnya. Daftar memaksanya mencari ulang siswa yang sama.
        //
        // `store()` sengaja tetap ke daftar — setelah menambah satu siswa,
        // yang biasanya menyusul adalah menambah siswa berikutnya.
        return to_route('admin.siswa.show', $siswa);
    }

    public function destroy(Student $siswa): RedirectResponse
    {
        $siswa->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data siswa berhasil dihapus.']);

        return to_route('admin.siswa.index');
    }
}
