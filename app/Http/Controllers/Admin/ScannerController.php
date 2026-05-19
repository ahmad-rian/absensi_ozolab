<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceType;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\Attendance\AttendanceRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        ]);

        $schoolId = auth()->user()->school_id;

        $student = Student::where('qr_token', $request->token)
            ->where('is_active', true)
            ->where('school_id', $schoolId)
            ->with(['classroom', 'parentProfile.user'])
            ->first();

        if (! $student) {
            // Coba cari berdasarkan NIS sebagai alternatif
            $student = Student::where('nis', $request->token)
                ->where('is_active', true)
                ->where('school_id', $schoolId)
                ->with(['classroom', 'parentProfile.user'])
                ->first();
        }

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'QR Code tidak dikenali atau siswa tidak aktif di sekolah ini.',
            ], 404);
        }

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
            'student' => $result['success'] ? [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'nis' => $student->nis,
                'classroom' => $student->classroom?->name,
                'status' => $result['attendance']?->status->label(),
                'type' => $type->label(),
                'time' => now()->format('H:i:s'),
            ] : null,
        ], $result['success'] ? 200 : 422);
    }
}
