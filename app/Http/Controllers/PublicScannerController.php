<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceType;
use App\Models\School;
use App\Services\Attendance\AttendanceRecorder;
use App\Services\Attendance\StudentLookup;
use App\Support\SchoolTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PublicScannerController extends Controller
{
    public function __construct(
        private readonly StudentLookup $studentLookup,
    ) {}

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

        $student = $this->studentLookup->find($request->token, $school->id);

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'QR Code tidak dikenali atau siswa tidak ditemukan.',
            ], 404);
        }

        // Tipe (masuk/pulang) ditentukan server dari jendela waktu jadwal.
        $result = $recorder->record(
            student: $student,
            recordedBy: null,
            deviceId: 'PUBLIC-SCAN',
        );

        $type = $result['attendance']?->type;

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
                'religion' => $student->religion?->label(),
                'birth_place' => $student->birth_place,
                'birth_date' => $student->birth_date?->translatedFormat('d F Y'),
                'address' => $student->address,
                'photo_url' => $student->photo_path
                    ? Storage::disk('public')->url($student->photo_path)
                    : null,
                'status' => $result['attendance']?->status->label(),
                'type' => $type?->value,
                'type_label' => $type === AttendanceType::CheckIn ? 'Masuk' : 'Pulang',
                'time' => SchoolTime::now()->format('H:i:s'),
            ] : null,
        ], $result['success'] ? 200 : 422);
    }
}
