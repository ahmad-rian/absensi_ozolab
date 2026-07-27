<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceType;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Services\Attendance\AttendanceRecorder;
use App\Services\Attendance\PrayerAttendanceRecorder;
use App\Services\Attendance\StudentLookup;
use App\Support\PrayerSettings;
use App\Support\SchoolTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ScannerController extends Controller
{
    public function __construct(
        private readonly StudentLookup $studentLookup,
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/scanner/index');
    }

    /**
     * Halaman scan absen sholat — sengaja terpisah dari scanner absensi
     * sekolah, bukan sebagai mode tambahan, supaya tidak bisa salah pencet.
     */
    public function prayerIndex(): Response
    {
        $school = School::find(auth()->user()->school_id);

        return Inertia::render('admin/scanner/prayer', [
            'prayer' => $school ? PrayerSettings::for($school)->toArray() : null,
        ]);
    }

    public function prayerScan(Request $request, PrayerAttendanceRecorder $recorder): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        $student = $this->studentLookup->find($request->token, auth()->user()->school_id);

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak dikenali. Siswa tidak ditemukan di sekolah ini.',
            ], 404);
        }

        $result = $recorder->record(
            student: $student,
            recordedBy: $request->user(),
            deviceId: 'WEB-SCANNER-SHOLAT',
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

    public function scan(Request $request, AttendanceRecorder $recorder): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'type' => ['sometimes', 'in:AUTO,CHECK_IN,CHECK_OUT'],
            'mode' => ['sometimes', 'in:attendance,validate'],
        ]);

        $schoolId = auth()->user()->school_id;
        $token = trim($request->token);

        $student = $this->studentLookup->find($token, $schoolId);

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

        // Mode absensi: catat kehadiran. Default AUTO — server yang menentukan
        // masuk/pulang dari jendela waktu jadwal. CHECK_IN/CHECK_OUT hanya
        // dipakai saat operator sengaja meng-override untuk koreksi.
        $type = AttendanceType::tryFrom($request->input('type', 'AUTO'));

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
                'type' => $result['attendance']?->type->label(),
                'time' => SchoolTime::now()->format('H:i:s'),
            ] : null,
        ], $result['success'] ? 200 : 422);
    }
}
