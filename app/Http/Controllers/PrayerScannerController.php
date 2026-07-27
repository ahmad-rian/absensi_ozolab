<?php

namespace App\Http\Controllers;

use App\Enums\PrayerType;
use App\Models\School;
use App\Services\Attendance\PrayerAttendanceRecorder;
use App\Services\Attendance\StudentLookup;
use App\Support\PrayerSchedule;
use App\Support\SchoolTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman scan khusus absen sholat, terpisah dari scanner absensi sekolah
 * supaya perangkat di mushola tidak bisa salah mode.
 *
 * Satu URL melayani Dhuha maupun Dzuhur: jenisnya ditentukan recorder dari jam
 * scan, karena kedua jendela dijamin tidak tumpang tindih.
 */
class PrayerScannerController extends Controller
{
    public function __construct(
        private readonly StudentLookup $studentLookup,
    ) {}

    public function index(School $school): Response
    {
        $schedule = PrayerSchedule::for($school);

        return Inertia::render('scan/prayer', [
            'school' => [
                'name' => $school->name,
                'logo_url' => $school->logo_path ? Storage::disk('public')->url($school->logo_path) : null,
                'is_active' => $school->is_active,
            ],
            'scanToken' => $school->scanner_token,
            // Prop lama dipertahankan (Dzuhur) supaya halaman yang belum
            // diperbarui dan test lama tetap jalan.
            'prayer' => $schedule->get(PrayerType::Dzuhur)->toArray(),
            'prayerSchedule' => $schedule->toArray(),
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

        $student = $this->studentLookup->findByQrToken($request->token, $school->id);

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'QR Code tidak dikenali atau siswa tidak ditemukan.',
            ], 404);
        }

        $result = $recorder->record(
            student: $student,
            recordedBy: null,
            // Dibiarkan null: recorder yang tahu jenis sholat mana yang kena,
            // jadi dia juga yang memilih label perangkatnya.
            deviceId: null,
        );

        // Bentuk payload sengaja disamakan dengan scanner absensi sekolah
        // supaya konsol scan di frontend bisa dipakai ulang apa adanya.
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
                // `type` tetap 'PRAYER' — frontend memakainya sebagai penanda
                // mode scanner; yang jadi dinamis adalah labelnya.
                'type' => 'PRAYER',
                'type_label' => $result['attendance']?->prayer_type->label() ?? 'Sholat',
                'time' => SchoolTime::now()->format('H:i:s'),
            ] : null,
        ], $result['success'] ? 200 : 422);
    }
}
