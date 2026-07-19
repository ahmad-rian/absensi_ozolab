<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CardGenerationLog;
use App\Models\Classroom;
use App\Models\ParentProfile;
use App\Models\Student;
use App\Services\Attendance\QrTokenGenerator;
use App\Services\PhotoSheetGeneratorService;
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
                        ->orWhere('nis', 'like', "%{$search}%");
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

    public function show(Student $siswa, QrTokenGenerator $qrGenerator): Response
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
                'created_at' => $log->created_at->format('d M Y H:i'),
            ]);

        $photoSheetTemplates = collect(PhotoSheetGeneratorService::TEMPLATES)
            ->map(fn (array $config, string $key) => ['value' => $key, 'label' => $config['label']])
            ->values();

        return Inertia::render('admin/siswa/show', [
            'student' => array_merge($studentData, [
                'religion_label' => $siswa->religion?->label(),
                'photo_url' => $siswa->photo_path
                    ? Storage::disk('public')->url($siswa->photo_path)
                    : null,
            ]),
            'qrSvg' => $qrSvg,
            'photoSheets' => $photoSheets,
            'photoSheetTemplates' => $photoSheetTemplates,
        ]);
    }

    public function qrCode(Student $siswa, QrTokenGenerator $qrGenerator): HttpResponse
    {
        $svg = $qrGenerator->renderSvg($siswa);
        $filename = 'qr-'.$siswa->nis.'-'.Str::slug($siswa->full_name).'.svg';

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
            'nis' => ['nullable', 'string', 'max:50', Rule::unique('students', 'nis')->where('school_id', auth()->user()->school_id)],
            'no_absen' => ['nullable', 'string', 'max:20'],
            'nisn' => ['nullable', 'string', 'max:50', Rule::unique('students', 'nisn')->where('school_id', auth()->user()->school_id)],
            'gender' => ['required', 'in:LAKI_LAKI,PEREMPUAN'],
            'religion' => ['nullable', 'in:ISLAM,KRISTEN,KATOLIK,HINDU,BUDDHA,KONGHUCU'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:120'],
            'parent_profile_id' => ['nullable', 'exists:parent_profiles,id'],
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
            'nis' => ['nullable', 'string', 'max:50', Rule::unique('students', 'nis')->where('school_id', auth()->user()->school_id)->ignore($siswa->id)],
            'no_absen' => ['nullable', 'string', 'max:20'],
            'nisn' => ['nullable', 'string', 'max:50', Rule::unique('students', 'nisn')->where('school_id', auth()->user()->school_id)->ignore($siswa->id)],
            'gender' => ['required', 'in:LAKI_LAKI,PEREMPUAN'],
            'religion' => ['nullable', 'in:ISLAM,KRISTEN,KATOLIK,HINDU,BUDDHA,KONGHUCU'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:120'],
            'parent_profile_id' => ['nullable', 'exists:parent_profiles,id'],
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

        return to_route('admin.siswa.index');
    }

    public function destroy(Student $siswa): RedirectResponse
    {
        $siswa->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data siswa berhasil dihapus.']);

        return to_route('admin.siswa.index');
    }
}
