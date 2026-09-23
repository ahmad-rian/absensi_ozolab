<?php

namespace App\Http\Controllers;

use App\Enums\SchoolFeature;
use App\Models\School;
use App\Services\Attendance\GerbangRecorder;
use App\Services\Attendance\StudentLookup;
use App\Support\PrayerSchedule;
use App\Support\ScannerShortLink;
use App\Support\ScanRejectionLog;
use App\Support\SchoolFeatures;
use App\Support\SchoolTime;
use App\Support\StudentPhotoStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class PublicScannerController extends Controller
{
    public function __construct(
        private readonly StudentLookup $studentLookup,
    ) {}

    /**
     * Apakah gerbang ini punya sesuatu untuk dicatat sama sekali.
     *
     * Bukan hanya `AbsensiSekolah`: sejak satu gerbang melayani datang, sholat,
     * dan pulang sekaligus, sekolah yang mematikan absensi sekolah tapi memakai
     * absen sholat akan terkunci dari pintunya sendiri kalau penjaganya cuma
     * melihat satu fitur. Yang mati tetap ditolak saat pencatatan, dengan pesan
     * yang menyebut sebabnya.
     */
    private static function gerbangTerbuka(School $school): bool
    {
        return SchoolFeatures::for($school)->enabled(SchoolFeature::AbsensiSekolah)
            || PrayerSchedule::for($school)->anyEnabled();
    }

    /**
     * Ringkasan jendela sholat untuk layar gerbang.
     *
     * @return array<int, array{label: string, jam: string, aktif: bool}>
     */
    private static function jendelaSholat(School $school): array
    {
        $jadwal = PrayerSchedule::for($school);
        $sekarang = $jadwal->resolveFor(SchoolTime::now());

        return array_map(fn ($jendela): array => [
            'label' => $jendela->type->shortLabel(),
            'jam' => $jendela->windowLabel(),
            'aktif' => $sekarang !== null && $sekarang->type === $jendela->type,
        ], $jadwal->enabled());
    }

    public function index(School $school): Response
    {
        return Inertia::render('scan/public', [
            'school' => [
                'name' => $school->name,
                'logo_url' => $school->logo_path ? Storage::disk('public')->url($school->logo_path) : null,
                'is_active' => $school->is_active,
            ],
            'scanToken' => $school->scanner_token,
            'jendelaSholat' => self::jendelaSholat($school),
            // Dipisah dari is_active supaya operator tahu bedanya "sekolah
            // nonaktif" dan "fitur absensi dimatikan admin". Halaman tetap 200:
            // tablet di dinding harus menampilkan pesan, bukan layar 403.
            'featureEnabled' => self::gerbangTerbuka($school),
        ]);
    }

    /**
     * Konsol scan versi ringan: Blade mandiri, tanpa React/Inertia/Vite.
     *
     * Dipakai perangkat gerbang berspesifikasi rendah — terutama box Android TV
     * yang browsernya terlalu tua untuk oklch() di app.css. Penjaganya sama
     * persis dengan index(), termasuk membedakan "sekolah nonaktif" dari "fitur
     * dimatikan admin", dan halamannya tetap 200 supaya layar di dinding
     * menampilkan pesan alih-alih 403.
     */
    public function light(School $school): View
    {
        return $this->lightView($school);
    }

    /**
     * `/g/{kode}` — alamat pendek menuju halaman scan ringan.
     *
     * Box Android TV di gerbang diketik pakai remote; 40 karakter scanner_token
     * tidak masuk akal untuk itu. Kode tidak dikenal atau ambigu dijawab 404,
     * sama seperti token yang salah.
     *
     * Halamannya dirender di sini, BUKAN dialihkan ke /scan/{token}/ringan.
     * Pengalihan akan menaruh token 40 karakter itu di bilah alamat, dan token
     * yang sama juga membuka gerbang sholat dan perpustakaan — sedangkan alias
     * seperti "tyas-photo" memang dibuat untuk gampang ditebak orang.
     */
    public function shortLink(string $kode): View
    {
        $school = ScannerShortLink::resolve($kode);

        abort_if($school === null, 404);

        return $this->lightView($school);
    }

    /**
     * `POST /g/{kode}` — endpoint scan milik halaman ringan.
     *
     * Alasannya sama dengan di atas: halaman ringan tidak boleh memuat
     * `scanner_token` di dalam HTML-nya, karena alamatnya sengaja gampang
     * ditebak. Logikanya tidak digandakan — keduanya masuk ke recordScan().
     */
    public function shortScan(Request $request, string $kode, GerbangRecorder $recorder): JsonResponse
    {
        $school = ScannerShortLink::resolve($kode);

        if ($school === null) {
            return response()->json([
                'success' => false,
                'message' => 'Halaman absensi tidak dikenali.',
            ], 404);
        }

        return $this->recordScan($request, $school, $recorder);
    }

    private function lightView(School $school): View
    {
        return view('scan.light', [
            'school' => $school,
            'logoUrl' => $school->logo_path ? Storage::disk('public')->url($school->logo_path) : null,
            'scanUrl' => route('public.scanner.short.scan', ['kode' => ScannerShortLink::codeFor($school)]),
            'featureEnabled' => self::gerbangTerbuka($school),
            // Jendela sholat yang berlaku hari ini, supaya layar menganggur
            // bisa menyebut apa yang sedang dibuka. Operator tidak perlu
            // menghafal jam Dhuha untuk tahu kenapa kartu yang sama menghasilkan
            // catatan berbeda di jam yang berbeda.
            'jendelaSholat' => self::jendelaSholat($school),
        ]);
    }

    public function scan(Request $request, School $school, GerbangRecorder $recorder): JsonResponse
    {
        return $this->recordScan($request, $school, $recorder);
    }

    private function recordScan(Request $request, School $school, GerbangRecorder $recorder): JsonResponse
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
        if (! self::gerbangTerbuka($school)) {
            return response()->json([
                'success' => false,
                'message' => 'Absensi sekolah sedang dimatikan oleh admin.',
            ], 403);
        }

        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $student = $this->studentLookup->findByQrToken($request->token, $school->id);

        // Pembaca RFID mode HID mengetik UID lalu Enter, persis seperti pemindai
        // QR — konsol scan tidak bisa membedakan keduanya, jadi server yang
        // mencoba UID kartu setelah token QR tidak cocok. Hanya saat fiturnya
        // dinyalakan: UID kartu jauh lebih pendek daripada qr_token, jadi jangan
        // membuka jalur tebakan itu di sekolah yang tidak memakai RFID.
        if (! $student && SchoolFeatures::for($school)->enabled(SchoolFeature::AbsensiRfid)) {
            $student = $this->studentLookup->findByRfidUid($request->token, $school->id);
        }

        if (! $student) {
            // Pesan ke layar sengaja sama untuk keempat sebab supaya tidak
            // membocorkan apa pun ke orang di depan gerbang. Bedanya ditulis ke
            // log, yang hanya bisa dibaca dari server.
            ScanRejectionLog::tolak($school, $request->token, 'absensi');

            return response()->json([
                'success' => false,
                'message' => 'Kartu atau QR Code tidak dikenali.',
            ], 404);
        }

        // Jenisnya ditentukan server, bukan client: masuk, Dhuha, Dzuhur, atau
        // pulang — yang mana pun yang belum tercatat pada jam ini.
        $result = $recorder->record(sekolahSiswa: $school, student: $student, deviceId: 'PUBLIC-SCAN');

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
                // Thumbnail kalau ada, foto asli kalau belum. Aslinya PNG
                // 1600 px berukuran megabita sementara layar gerbang cuma
                // menggambarnya 240×320 — di box Android TV dengan wifi
                // sekolah, itu yang membuat jeda antara kartu ditempel dan
                // wajah muncul terasa panjang.
                'photo_url' => StudentPhotoStorage::displayUrl($student->photo_path),
                'status' => $result['status'],
                'type' => $result['jenis'],
                'type_label' => $result['label'],
                'time' => SchoolTime::now()->format('H:i:s'),
            ] : null,
        ], $result['success'] ? 200 : 422);
    }
}
