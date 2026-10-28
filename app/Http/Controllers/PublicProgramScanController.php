<?php

namespace App\Http\Controllers;

use App\Models\CardScan;
use App\Models\Kehadiran;
use App\Models\Program;
use App\Models\Sesi;
use App\Services\CardAttendanceService;
use App\Services\Penyelenggara\KehadiranRecorder;
use App\Services\RiwayatScan;
use App\Support\ProgramShortLink;
use App\Support\StudentPhotoStorage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman scan program penyelenggara (spec 004): kamera `/hadir/{token}` dan
 * ringan `/h/{kode}`. Terpisah total dari scan Kartu Bebas (`/p`, `/scan-peserta`).
 */
class PublicProgramScanController extends Controller
{
    public function __construct(
        private KehadiranRecorder $recorder,
        private CardAttendanceService $kartu,
    ) {}

    public function halaman(string $kode): Response
    {
        $program = ProgramShortLink::fromToken($kode);
        abort_if($program === null, 404);

        return Inertia::render('scan/program', [
            'program' => ['name' => $program->name, 'aktif' => ! $program->isArchived()],
            'scanUrl' => route('public.program-scan.scan', $kode),
            'riwayatUrl' => route('public.program-scan.riwayat', $kode),
        ]);
    }

    public function scan(Request $request, string $kode): JsonResponse
    {
        return $this->rekam($request, ProgramShortLink::fromToken($kode));
    }

    public function ringan(string $kode): View
    {
        $program = ProgramShortLink::resolve($kode);
        abort_if($program === null, 404);

        return view('scan.light', [
            'judul' => $program->name,
            'aktif' => ! $program->isArchived(),
            'pesanMati' => 'Program ini sudah diarsip.',
            'featureEnabled' => true,
            'logoUrl' => null,
            'petunjuk' => 'Tempelkan kartu atau tembak QR peserta — jam datang/pulang mengikuti jadwal sesi',
            'scanUrl' => route('public.program-scan.ringan.scan', $kode),
            'riwayatUrl' => route('public.program-scan.ringan.riwayat', $kode),
            'kameraUrl' => route('public.program-scan.ringan.kamera', $kode),
            'jendelaSholat' => [],
        ]);
    }

    /**
     * Konsol kamera untuk HP lewat kode pendek (`/h/{kode}/kamera`).
     */
    public function kameraRingan(string $kode): Response
    {
        $program = ProgramShortLink::resolve($kode);
        abort_if($program === null, 404);

        return Inertia::render('scan/program', [
            'program' => ['name' => $program->name, 'aktif' => ! $program->isArchived()],
            'scanUrl' => route('public.program-scan.ringan.scan', $kode, false),
            'riwayatUrl' => route('public.program-scan.ringan.riwayat', $kode, false),
        ]);
    }

    public function scanRingan(Request $request, string $kode): JsonResponse
    {
        return $this->rekam($request, ProgramShortLink::resolve($kode));
    }

    /**
     * Riwayat scan hari ini (kamera `/hadir` maupun ringan `/h`).
     */
    public function riwayat(string $kode): JsonResponse
    {
        $program = ProgramShortLink::fromToken($kode) ?? ProgramShortLink::resolve($kode);
        abort_if($program === null || $program->isArchived(), 404);

        return response()->json(['riwayat' => app(RiwayatScan::class)->program($program)]);
    }

    private function rekam(Request $request, ?Program $program): JsonResponse
    {
        if ($program === null || $program->isArchived()) {
            return response()->json(['success' => false, 'message' => 'Halaman scan tidak dikenali atau program sudah diarsip.', 'student' => null], 404);
        }

        $data = $request->validate(['token' => ['required', 'string', 'max:150']]);

        try {
            $hasil = $this->recorder->scan($program, $data['token']);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first(), 'student' => null], 422);
        }

        $kehadiran = $hasil['kehadiran'];
        // Scan di antara jendela (mode scan) tampil sebagai "Datang": jam datangnya tetap.
        $pulang = $hasil['mode'] === KehadiranRecorder::MODE_PULANG;
        $label = $pulang ? 'Pulang' : 'Datang';
        $jam = ($pulang ? $kehadiran->pulang_at : $kehadiran->datang_at)?->format('H:i');

        return response()->json([
            'success' => true,
            'duplikat' => $hasil['duplikat'],
            'riwayat' => app(RiwayatScan::class)->program($program),
            'message' => $this->pesanScan($hasil, $label),
            'student' => [
                'full_name' => $this->kartu->participantName($hasil['peserta']),
                'nis' => null,
                'no_absen' => null,
                'classroom' => $hasil['sesi']->name,
                'photo_url' => StudentPhotoStorage::displayUrl($hasil['peserta']->photo_path),
                'status' => $kehadiran->status,
                'type' => $pulang ? 'CHECK_OUT' : 'CHECK_IN',
                'type_label' => $label,
                'time' => $jam,
            ],
        ]);
    }

    /**
     * Pesan layar petugas. Scan berulang selalu diterima; hanya scan dobel
     * dalam jeda singkat yang disebut "baru saja tercatat".
     *
     * @param  array{duplikat: bool, jenis: ?string, urutan: int, sesi: Sesi, kehadiran: Kehadiran}  $hasil
     */
    private function pesanScan(array $hasil, string $label): string
    {
        $sesi = $hasil['sesi']->name;
        $datang = $hasil['kehadiran']->datang_at?->format('H:i');
        $pulang = $hasil['kehadiran']->pulang_at?->format('H:i');

        return match (true) {
            $hasil['duplikat'] => "Baru saja tercatat · {$sesi}. Tunggu sebentar sebelum scan lagi.",
            $hasil['jenis'] === CardScan::JENIS_DATANG => "Datang tercatat {$datang} · {$sesi}.",
            $hasil['jenis'] === CardScan::JENIS_PULANG && $hasil['urutan'] > 2 => "Pulang diperbarui {$pulang} · scan ke-{$hasil['urutan']} · {$sesi}.",
            $hasil['jenis'] === CardScan::JENIS_PULANG => "Pulang tercatat {$pulang} · {$sesi}.",
            default => "Scan ke-{$hasil['urutan']} tercatat · datang {$datang} · {$sesi}.",
        };
    }
}
