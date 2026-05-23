<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceType;
use App\Models\School;
use App\Models\Student;
use App\Services\Attendance\AttendanceRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PublicScannerController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $school = School::find($user->school_id);

        return Inertia::render('scanner', [
            'school' => $school ? [
                'id' => $school->id,
                'name' => $school->name,
                'slug' => $school->slug,
                'logo_url' => $school->logo_path ? Storage::disk('public')->url($school->logo_path) : null,
            ] : null,
            'userName' => $user->name,
        ]);
    }

    public function scan(Request $request, AttendanceRecorder $recorder): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'type' => ['sometimes', 'in:CHECK_IN,CHECK_OUT'],
        ]);

        $schoolId = $request->user()->school_id;

        // Search: QR token → NISN → NIS
        $student = Student::where('qr_token', $request->token)
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->with('classroom')
            ->first();

        if (! $student) {
            $student = Student::where('nisn', $request->token)
                ->where('school_id', $schoolId)
                ->where('is_active', true)
                ->with('classroom')
                ->first();
        }

        if (! $student) {
            $student = Student::where('nis', $request->token)
                ->where('school_id', $schoolId)
                ->where('is_active', true)
                ->with('classroom')
                ->first();
        }

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'QR Code tidak dikenali atau siswa tidak ditemukan.',
            ], 404);
        }

        $type = AttendanceType::from($request->input('type', 'CHECK_IN'));

        $result = $recorder->record(
            student: $student,
            type: $type,
            recordedBy: $request->user(),
            deviceId: 'SCAN-PAGE',
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
                'type' => $type->label(),
                'time' => now('Asia/Jakarta')->format('H:i:s'),
            ] : null,
        ], $result['success'] ? 200 : 422);
    }
}
