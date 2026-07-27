<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceType;
use App\Enums\SchoolFeature;
use App\Models\School;
use App\Services\Attendance\AttendanceRecorder;
use App\Services\Attendance\StudentLookup;
use App\Support\SchoolFeatures;
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
            // Dipisah dari is_active supaya operator tahu bedanya "sekolah
            // nonaktif" dan "fitur absensi dimatikan admin". Halaman tetap 200:
            // tablet di dinding harus menampilkan pesan, bukan layar 403.
            'featureEnabled' => SchoolFeatures::for($school)->enabled(SchoolFeature::AbsensiSekolah),
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

        // Guard fitur sengaja di controller, bukan middleware: `abort(403)`
        // menghasilkan halaman HTML/Inertia, sedangkan konsol scan memanggil
        // endpoint ini dengan fetch dan hanya membaca {success, message}.
        if (SchoolFeatures::for($school)->disabled(SchoolFeature::AbsensiSekolah)) {
            return response()->json([
                'success' => false,
                'message' => 'Absensi sekolah sedang dimatikan oleh admin.',
            ], 403);
        }

        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $student = $this->studentLookup->findByQrToken($request->token, $school->id);

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
                // Layar gerbang hanya butuh identitas seperlunya. Alamat,
                // tanggal lahir, agama, dan NISN sengaja tidak dikirim — itu
                // yang dulu membuat endpoint ini jadi alat panen PII.
                'full_name' => $student->full_name,
                'nis' => $student->nis,
                'no_absen' => $student->no_absen,
                'classroom' => $student->classroom?->name,
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
