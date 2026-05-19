<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceType;
use App\Models\School;
use App\Models\Student;
use App\Services\Attendance\AttendanceRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicScannerController extends Controller
{
    public function index(Request $request): Response
    {
        $schools = School::where('is_active', true)->get(['id', 'name', 'slug']);

        return Inertia::render('scanner', [
            'schools' => $schools,
        ]);
    }

    public function scan(Request $request, AttendanceRecorder $recorder): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'school_id' => ['required', 'exists:schools,id'],
            'type' => ['sometimes', 'in:CHECK_IN,CHECK_OUT'],
        ]);

        $student = Student::where('qr_token', $request->token)
            ->where('school_id', $request->school_id)
            ->where('is_active', true)
            ->with('classroom')
            ->first();

        if (! $student) {
            $student = Student::where('nis', $request->token)
                ->where('school_id', $request->school_id)
                ->where('is_active', true)
                ->with('classroom')
                ->first();
        }

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'QR Code tidak dikenali.',
            ], 404);
        }

        $type = AttendanceType::from($request->input('type', 'CHECK_IN'));

        $result = $recorder->record(
            student: $student,
            type: $type,
            deviceId: 'PUBLIC-SCANNER',
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'student' => $result['success'] ? [
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
