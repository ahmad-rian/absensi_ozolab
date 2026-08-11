<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Student;
use App\Services\Attendance\StudentLookup;
use App\Support\SchoolTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pendaftaran kartu RFID ke siswa.
 *
 * Yang disimpan adalah UID kartu — angka yang sudah dibakar pabrik ke dalam
 * chip dan bersifat baca-saja. Tidak ada yang ditulis ke kartunya; pembaca mode
 * HID mengetikkan UID itu seperti keyboard, dan halaman ini menangkapnya.
 */
class RfidCardController extends Controller
{
    public function index(Request $request): Response
    {
        $students = Student::forSchool()
            ->with('classroom:id,name')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%")
                        ->orWhere('rfid_uid', 'like', "%{$search}%");
                });
            })
            ->when($request->classroom_id, fn ($query, $classroomId) => $query->where('classroom_id', $classroomId))
            ->when($request->status === 'terdaftar', fn ($query) => $query->whereNotNull('rfid_uid'))
            ->when($request->status === 'belum', fn ($query) => $query->whereNull('rfid_uid'))
            ->orderBy('full_name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Student $student) => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'nis' => $student->nis,
                'nisn' => $student->nisn,
                'classroom' => $student->classroom?->name,
                'is_active' => $student->is_active,
                'rfid_uid' => $student->rfid_uid,
                'rfid_registered_at' => $student->rfid_registered_at?->format('d M Y H:i'),
            ]);

        return Inertia::render('admin/rfid-cards/index', [
            'students' => $students,
            'classrooms' => Classroom::forSchool()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $request->search ?? '',
                'classroom_id' => $request->classroom_id ?? '',
                'status' => $request->status ?? '',
            ],
            'summary' => [
                'total' => Student::forSchool()->count(),
                'registered' => Student::forSchool()->whereNotNull('rfid_uid')->count(),
            ],
        ]);
    }

    public function store(Request $request, Student $siswa): RedirectResponse
    {
        $validated = $request->validate([
            'rfid_uid' => ['required', 'string', 'max:64'],
        ], [
            'rfid_uid.required' => 'Tempelkan kartu ke pembaca terlebih dahulu.',
        ]);

        $uid = StudentLookup::normalizeRfidUid($validated['rfid_uid']);

        if ($uid === '') {
            return back()->withErrors(['rfid_uid' => 'UID kartu tidak terbaca. Coba tempelkan ulang.']);
        }

        // Kolomnya unique global dan tabel ini memakai soft delete, jadi kartu
        // milik siswa yang sudah dihapus tetap menahan UID-nya. Dicek lebih dulu
        // supaya pesannya menyebut siapa pemiliknya, bukan melempar galat SQL.
        $owner = Student::acrossSchools()
            ->withTrashed()
            ->where('rfid_uid', $uid)
            ->where('id', '!=', $siswa->id)
            ->first();

        if ($owner) {
            return back()->withErrors([
                'rfid_uid' => $owner->school_id === $siswa->school_id
                    ? sprintf('Kartu ini sudah terdaftar atas nama %s.', $owner->full_name)
                    : 'Kartu ini sudah terdaftar di sekolah lain.',
            ]);
        }

        $siswa->update([
            'rfid_uid' => $uid,
            'rfid_registered_at' => SchoolTime::now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => sprintf('Kartu %s didaftarkan untuk %s.', $uid, $siswa->full_name)]);

        return back();
    }

    public function destroy(Student $siswa): RedirectResponse
    {
        $siswa->update(['rfid_uid' => null, 'rfid_registered_at' => null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => sprintf('Kartu %s dilepas.', $siswa->full_name)]);

        return back();
    }
}
