<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use App\Services\Attendance\AttendanceRecorder;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AbsensiController extends Controller
{
    public function index(Request $request): Response
    {
        $schoolId = auth()->user()->school_id;

        $query = Attendance::with(['student.classroom'])
            ->whereHas('student', fn ($q) => $q->where('school_id', $schoolId))
            ->orderByDesc('recorded_at');

        $date = $request->query('date', now()->toDateString());
        $query->whereDate('attendance_date', $date);

        if ($request->filled('classroom_id')) {
            $query->whereHas('student', function ($q) use ($request): void {
                $q->where('classroom_id', $request->query('classroom_id'));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        $attendances = $query->paginate(20)->withQueryString();

        $classrooms = Classroom::forSchool()->orderBy('name')->get(['id', 'name']);

        $students = Student::forSchool()
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'nis', 'full_name', 'classroom_id', 'school_id']);

        return Inertia::render('admin/absensi/index', [
            'attendances' => $attendances,
            'classrooms' => $classrooms,
            'students' => $students,
            'filters' => [
                'date' => $date,
                'classroom_id' => $request->query('classroom_id', ''),
                'status' => $request->query('status', ''),
                'type' => $request->query('type', ''),
            ],
        ]);
    }

    public function store(Request $request, AttendanceRecorder $recorder): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'type' => ['required', 'in:CHECK_IN,CHECK_OUT'],
            'status' => ['required', 'in:HADIR,TERLAMBAT,ALPA,IZIN,SAKIT'],
            'notes' => ['nullable', 'string', 'max:500'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $student = Student::findOrFail($validated['student_id']);

        // Verifikasi siswa milik sekolah yang sama
        if ($student->school_id !== auth()->user()->school_id) {
            return back()->with('error', 'Siswa tidak ditemukan di sekolah Anda.');
        }

        $type = AttendanceType::from($validated['type']);
        // recorded_at disimpan sebagai WIB wall-clock (konsisten dgn AttendanceRecorder).
        $timestamp = isset($validated['recorded_at']) ? Carbon::parse($validated['recorded_at']) : Carbon::now('Asia/Jakarta');

        $status = AttendanceStatus::from($validated['status']);

        // For manual input with explicit status, create directly instead of using the recorder
        // which determines status based on schedule
        try {
            Attendance::create([
                'student_id' => $student->id,
                'attendance_date' => $timestamp->toDateString(),
                'type' => $type,
                'status' => $status,
                'recorded_at' => $timestamp,
                'recorded_by' => $request->user()->id,
                'notes' => $validated['notes'] ?? null,
            ]);
        } catch (UniqueConstraintViolationException) {
            return back()->with('error', 'Siswa sudah melakukan '.($type === AttendanceType::CheckIn ? 'check-in' : 'check-out').' pada tanggal tersebut.');
        }

        return back()->with('success', 'Absensi berhasil dicatat.');
    }
}
