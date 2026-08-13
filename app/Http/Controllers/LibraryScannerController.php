<?php

namespace App\Http\Controllers;

use App\Enums\SchoolFeature;
use App\Models\School;
use App\Services\Attendance\LibraryVisitRecorder;
use App\Services\Attendance\StudentLookup;
use App\Support\SchoolFeatures;
use App\Support\SchoolTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Scan kunjungan perpustakaan.
 *
 * Satu tap membuka kunjungan, tap berikutnya menutupnya — petugas tidak memilih
 * mode. Yang memutuskan masuk atau keluar adalah recorder, dari keadaan kunjungan
 * siswa itu hari ini.
 */
class LibraryScannerController extends Controller
{
    public function __construct(
        private readonly StudentLookup $studentLookup,
    ) {}

    public function index(School $school): Response
    {
        return Inertia::render('scan/library', [
            'school' => [
                'name' => $school->name,
                'logo_url' => $school->logo_path ? Storage::disk('public')->url($school->logo_path) : null,
                'is_active' => $school->is_active,
            ],
            'scanToken' => $school->scanner_token,
            // Dipisah dari is_active supaya petugas tahu bedanya "sekolah
            // nonaktif" dan "fitur dimatikan admin". Halaman tetap 200: tablet
            // di perpustakaan harus menampilkan pesan, bukan layar 403.
            'featureEnabled' => SchoolFeatures::for($school)->enabled(SchoolFeature::KunjunganPerpustakaan),
        ]);
    }

    public function scan(Request $request, School $school, LibraryVisitRecorder $recorder): JsonResponse
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

        // Kartu perpustakaan tercetak memakai QR, tapi kartu RFID yang sudah
        // didaftarkan juga berlaku di sini — pola yang sama dengan gerbang, dan
        // hanya dicoba saat fiturnya menyala.
        if (! $student && SchoolFeatures::for($school)->enabled(SchoolFeature::AbsensiRfid)) {
            $student = $this->studentLookup->findByRfidUid($request->token, $school->id);
        }

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'Kartu atau QR Code tidak dikenali.',
            ], 404);
        }

        $result = $recorder->record(student: $student, deviceId: 'PERPUS-SCAN');

        $visit = $result['visit'];
        $keluar = $result['action'] === LibraryVisitRecorder::ACTION_EXIT;

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'student' => $result['success'] ? [
                // Identitas seperlunya saja, sama seperti jalur scan yang lain.
                'full_name' => $student->full_name,
                'nis' => $student->nis,
                'no_absen' => $student->no_absen,
                'classroom' => $student->classroom?->name,
                'photo_url' => $student->photo_path
                    ? Storage::disk('public')->url($student->photo_path)
                    : null,
                'status' => $keluar ? $this->durationLabel($visit?->durationMinutes()) : 'Masuk',
                // `type` dipakai frontend sebagai penanda mode; labelnya yang dinamis.
                'type' => 'LIBRARY',
                'type_label' => $keluar ? 'Keluar Perpustakaan' : 'Masuk Perpustakaan',
                'time' => SchoolTime::now()->format('H:i:s'),
            ] : null,
        ], $result['success'] ? 200 : 422);
    }

    private function durationLabel(?int $minutes): string
    {
        if ($minutes === null) {
            return 'Keluar';
        }

        if ($minutes < 60) {
            return $minutes.' menit';
        }

        $jam = intdiv($minutes, 60);
        $sisa = $minutes % 60;

        return $sisa === 0 ? $jam.' jam' : $jam.' jam '.$sisa.' menit';
    }
}
