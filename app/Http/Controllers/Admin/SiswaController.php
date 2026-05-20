<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ParentProfile;
use App\Models\Student;
use App\Services\Attendance\QrTokenGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
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

        return Inertia::render('admin/siswa/show', [
            'student' => array_merge($siswa->toArray(), [
                'religion_label' => $siswa->religion?->label(),
            ]),
            'qrSvg' => $qrSvg,
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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'nis' => ['nullable', 'string', 'max:50', 'unique:students,nis'],
            'no_absen' => ['nullable', 'string', 'max:20'],
            'nisn' => ['nullable', 'string', 'max:50', 'unique:students,nisn'],
            'gender' => ['required', 'in:LAKI_LAKI,PEREMPUAN'],
            'religion' => ['nullable', 'in:ISLAM,KRISTEN,KATOLIK,HINDU,BUDDHA,KONGHUCU'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string'],
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

        $validated['qr_token'] = $validated['qr_token'] ?? Str::random(64);
        $validated['qr_issued_at'] = now();

        // school_id diisi otomatis oleh trait BelongsToSchool
        Student::create($validated);

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
            'nis' => ['nullable', 'string', 'max:50', 'unique:students,nis,'.$siswa->id],
            'no_absen' => ['nullable', 'string', 'max:20'],
            'nisn' => ['nullable', 'string', 'max:50', 'unique:students,nisn,'.$siswa->id],
            'gender' => ['required', 'in:LAKI_LAKI,PEREMPUAN'],
            'religion' => ['nullable', 'in:ISLAM,KRISTEN,KATOLIK,HINDU,BUDDHA,KONGHUCU'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string'],
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
