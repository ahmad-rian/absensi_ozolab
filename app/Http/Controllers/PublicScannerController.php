<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\School;
use App\Models\Student;
use App\Services\Attendance\AttendanceRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PublicScannerController extends Controller
{
    public function index(School $school): Response
    {
        return Inertia::render('scan/public', [
            'school' => [
                'name' => $school->name,
                'logo_url' => $school->logo_path ? Storage::disk('public')->url($school->logo_path) : null,
                'is_active' => $school->is_active,
            ],
            'scanToken' => $school->scanner_token,
        ]);
    }

    public function scan(Request $request, School $school, AttendanceRecorder $recorder): JsonResponse
    {
        if (! $school->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Halaman absensi sekolah ini sedang tidak aktif.',
            ], 403);
        }

        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $student = $this->findStudent($request->token, $school->id);

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'QR Code tidak dikenali atau siswa tidak ditemukan.',
            ], 404);
        }

        $type = $this->determineType($student);

        if ($type === null) {
            return response()->json([
                'success' => false,
                'message' => $student->full_name.' sudah absen masuk & pulang hari ini.',
            ], 422);
        }

        $result = $recorder->record(
            student: $student,
            type: $type,
            recordedBy: null,
            deviceId: 'PUBLIC-SCAN',
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'student' => $result['success'] ? [
                'full_name' => $student->full_name,
                'nis' => $student->nis,
                'nisn' => $student->nisn,
                'no_absen' => $student->no_absen,
                'classroom' => $student->classroom?->name,
                'gender' => $student->gender?->label(),
                'photo_url' => $student->photo_path
                    ? Storage::disk('public')->url($student->photo_path)
                    : null,
                'status' => $result['attendance']?->status->label(),
                'type' => $type->value,
                'type_label' => $type === AttendanceType::CheckIn ? 'Masuk' : 'Pulang',
                'time' => now('Asia/Jakarta')->format('H:i:s'),
            ] : null,
        ], $result['success'] ? 200 : 422);
    }

    /**
     * Cari siswa aktif berdasarkan QR token → NISN → NIS, dibatasi sekolah.
     */
    private function findStudent(string $token, string $schoolId): ?Student
    {
        foreach (['qr_token', 'nisn', 'nis'] as $column) {
            $student = Student::where($column, $token)
                ->where('school_id', $schoolId)
                ->where('is_active', true)
                ->with('classroom')
                ->first();

            if ($student) {
                return $student;
            }
        }

        return null;
    }

    /**
     * Deteksi otomatis: belum absen → Masuk, sudah Masuk → Pulang, keduanya → null.
     */
    private function determineType(Student $student): ?AttendanceType
    {
        $date = Carbon::now('Asia/Jakarta')->toDateString();

        $hasCheckIn = Attendance::where('student_id', $student->id)
            ->whereDate('attendance_date', $date)
            ->where('type', AttendanceType::CheckIn)
            ->exists();

        if (! $hasCheckIn) {
            return AttendanceType::CheckIn;
        }

        $hasCheckOut = Attendance::where('student_id', $student->id)
            ->whereDate('attendance_date', $date)
            ->where('type', AttendanceType::CheckOut)
            ->exists();

        if (! $hasCheckOut) {
            return AttendanceType::CheckOut;
        }

        return null;
    }
}
