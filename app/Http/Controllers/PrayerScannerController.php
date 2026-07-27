<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Services\Attendance\PrayerAttendanceRecorder;
use App\Services\Attendance\StudentLookup;
use App\Support\PrayerSettings;
use App\Support\SchoolTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman scan khusus absen sholat dzuhur, terpisah dari scanner absensi
 * sekolah supaya device di mushola tidak bisa salah mode.
 */
class PrayerScannerController extends Controller
{
    public function __construct(
        private readonly StudentLookup $studentLookup,
    ) {}

    public function index(School $school): Response
    {
        $settings = PrayerSettings::for($school);

        return Inertia::render('scan/prayer', [
            'school' => [
                'name' => $school->name,
                'logo_url' => $school->logo_path ? Storage::disk('public')->url($school->logo_path) : null,
                'is_active' => $school->is_active,
            ],
            'scanToken' => $school->scanner_token,
            'prayer' => $settings->toArray(),
        ]);
    }

    public function scan(Request $request, School $school, PrayerAttendanceRecorder $recorder): JsonResponse
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

        $result = $recorder->record(
            student: $student,
            recordedBy: null,
            deviceId: 'PRAYER-SCAN',
        );

        // Bentuk payload sengaja disamakan dengan scanner absensi sekolah
        // supaya konsol scan di frontend bisa dipakai ulang apa adanya.
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
                'type' => 'PRAYER',
                'type_label' => 'Sholat Dzuhur',
                'time' => SchoolTime::now()->format('H:i:s'),
            ] : null,
        ], $result['success'] ? 200 : 422);
    }
}
