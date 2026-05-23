<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceType;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\Attendance\AttendanceRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ScannerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/scanner/index');
    }

    public function scan(Request $request, AttendanceRecorder $recorder): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'type' => ['sometimes', 'in:CHECK_IN,CHECK_OUT'],
            'mode' => ['sometimes', 'in:attendance,validate'],
        ]);

        $schoolId = auth()->user()->school_id;
        $token = trim($request->token);

        $student = $this->findStudent($token, $schoolId);

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak dikenali. Siswa tidak ditemukan di sekolah ini.',
            ], 404);
        }

        // Mode validasi: hanya cek data siswa, tanpa mencatat absensi
        if ($request->input('mode') === 'validate') {
            return response()->json([
                'success' => true,
                'message' => 'Data siswa valid.',
                'mode' => 'validate',
                'student' => [
                    'id' => $student->id,
                    'full_name' => $student->full_name,
                    'nis' => $student->nis,
                    'nisn' => $student->nisn,
                    'no_absen' => $student->no_absen,
                    'classroom' => $student->classroom?->name,
                    'gender' => $student->gender?->label(),
                    'photo_url' => $student->photo_path
                        ? Storage::disk('public')->url($student->photo_path)
                        : null,
                    'is_active' => $student->is_active,
                    'has_qr' => (bool) $student->qr_token,
                ],
            ]);
        }

        // Mode absensi: catat kehadiran
        $type = AttendanceType::from($request->input('type', 'CHECK_IN'));

        $result = $recorder->record(
            student: $student,
            type: $type,
            recordedBy: $request->user(),
            deviceId: 'WEB-SCANNER',
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'mode' => 'attendance',
            'student' => $result['success'] ? [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'nis' => $student->nis,
                'classroom' => $student->classroom?->name,
                'photo_url' => $student->photo_path
                    ? Storage::disk('public')->url($student->photo_path)
                    : null,
                'status' => $result['attendance']?->status->label(),
                'type' => $type->label(),
                'time' => now('Asia/Jakarta')->format('H:i:s'),
            ] : null,
        ], $result['success'] ? 200 : 422);
    }

    /**
     * Cari siswa berdasarkan QR token, NISN, atau NIS.
     */
    private function findStudent(string $token, int $schoolId): ?Student
    {
        // 1. Cari berdasarkan QR token (barcode gun / camera scan)
        $student = Student::where('qr_token', $token)
            ->where('is_active', true)
            ->where('school_id', $schoolId)
            ->with('classroom')
            ->first();

        if ($student) {
            return $student;
        }

        // 2. Cari berdasarkan NISN (unik nasional, prioritas untuk input manual)
        $student = Student::where('nisn', $token)
            ->where('is_active', true)
            ->where('school_id', $schoolId)
            ->with('classroom')
            ->first();

        if ($student) {
            return $student;
        }

        // 3. Fallback ke NIS (mungkin sama antar sekolah, tapi sudah di-scope by school_id)
        return Student::where('nis', $token)
            ->where('is_active', true)
            ->where('school_id', $schoolId)
            ->with('classroom')
            ->first();
    }
}
