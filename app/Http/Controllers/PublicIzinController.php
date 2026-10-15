<?php

namespace App\Http\Controllers;

use App\Enums\SchoolFeature;
use App\Models\PengajuanIzin;
use App\Models\School;
use App\Models\Student;
use App\Services\Perizinan\PerizinanService;
use App\Support\SchoolFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Form izin/sakit publik `/izin` untuk orang tua yang belum punya akun portal.
 * Multi-langkah: captcha → pilih sekolah → cari anak → alasan & bukti.
 *
 * Captcha dicek di server (jawaban disimpan di session), bukan hanya di
 * browser. Pencarian siswa dan pengiriman hanya terbuka setelah lolos captcha
 * dan dibatasi throttle, karena halaman ini terbuka untuk siapa saja.
 */
class PublicIzinController extends Controller
{
    private const SESI_JAWABAN = 'izin_captcha';

    private const SESI_LOLOS = 'izin_lolos_sampai';

    private const SESI_LOLOS_PADA = 'izin_lolos_pada';

    private const SESI_CARI = 'izin_jumlah_cari';

    /** Jawaban salah sebelum dikunci, dan lama kuncinya (detik). */
    private const MAKS_SALAH = 5;

    private const DETIK_KUNCI = 900;

    /** Pencarian siswa per satu kali lolos captcha. */
    private const MAKS_CARI = 30;

    /** Jeda minimum antara lolos captcha dan kirim — manusia butuh waktu mengisi. */
    private const MIN_DETIK_ISI = 5;

    /** Pengajuan publik per siswa per hari. */
    private const MAKS_PER_SISWA_HARIAN = 3;

    /** Lama akses setelah lolos captcha, dalam menit. */
    private const MENIT_LOLOS = 30;

    private const MAKS_HASIL = 10;

    public function index(Request $request): Response
    {
        return Inertia::render('public/izin', [
            'sekolah' => $this->sekolahAktif()->map(fn (School $s) => ['id' => $s->id, 'name' => $s->name, 'city' => $s->city])->values(),
            'captcha' => ['soal' => $this->soalBaru($request)],
            'terverifikasi' => $this->lolos($request),
            'maksHari' => PengajuanIzin::MAKS_HARI,
        ]);
    }

    public function verifikasi(Request $request): JsonResponse
    {
        $kunciSalah = 'izin-salah:'.$request->ip();
        if (RateLimiter::tooManyAttempts($kunciSalah, self::MAKS_SALAH)) {
            $menit = (int) ceil(RateLimiter::availableIn($kunciSalah) / 60);

            return response()->json(['message' => "Terlalu banyak jawaban salah. Coba lagi {$menit} menit lagi."], 429);
        }

        $jawaban = $request->validate(['jawaban' => ['required', 'integer']])['jawaban'];
        $benar = $request->session()->pull(self::SESI_JAWABAN);

        if ($benar === null || (int) $jawaban !== (int) $benar) {
            RateLimiter::hit($kunciSalah, self::DETIK_KUNCI);

            return response()->json([
                'message' => 'Jawaban belum tepat. Coba soal yang baru.',
                'soal' => $this->soalBaru($request),
            ], 422);
        }

        RateLimiter::clear($kunciSalah);
        $request->session()->put(self::SESI_LOLOS, now()->addMinutes(self::MENIT_LOLOS)->timestamp);
        $request->session()->put(self::SESI_LOLOS_PADA, now()->timestamp);
        $request->session()->put(self::SESI_CARI, 0);

        return response()->json(['ok' => true]);
    }

    public function siswa(Request $request): JsonResponse
    {
        abort_unless($this->lolos($request), 403, 'Selesaikan pertanyaan keamanan dulu.');

        // Kuota pencarian per sesi captcha: mencegah daftar siswa disedot
        // dengan menebak-nebak nama berulang kali.
        $jumlah = (int) $request->session()->get(self::SESI_CARI, 0) + 1;
        if ($jumlah > self::MAKS_CARI) {
            $request->session()->forget(self::SESI_LOLOS);
            abort(403, 'Batas pencarian tercapai. Jawab pertanyaan keamanan lagi.');
        }
        $request->session()->put(self::SESI_CARI, $jumlah);

        $data = $request->validate([
            'school_id' => ['required', 'string'],
            'q' => ['required', 'string', 'min:3', 'max:60'],
        ], ['q.min' => 'Ketik minimal 3 huruf nama anak.']);

        $sekolah = $this->sekolahAktif()->firstWhere('id', $data['school_id']);
        abort_unless($sekolah, 404);

        $siswa = Student::withoutGlobalScope('school')
            ->where('school_id', $sekolah->id)
            ->where('is_active', true)
            ->where('full_name', 'like', '%'.addcslashes(trim($data['q']), '%_\\').'%')
            ->with(['classroom' => fn ($q) => $q->withoutGlobalScope('school')->select('id', 'name')])
            ->orderBy('full_name')
            ->limit(self::MAKS_HASIL)
            ->get(['id', 'full_name', 'classroom_id']);

        return response()->json([
            'siswa' => $siswa->map(fn (Student $s) => ['id' => $s->id, 'nama' => $s->full_name, 'kelas' => $s->classroom?->name])->values(),
        ]);
    }

    public function store(Request $request, PerizinanService $perizinan): RedirectResponse
    {
        abort_unless($this->lolos($request), 403, 'Sesi pengajuan habis. Muat ulang halaman dan jawab pertanyaan keamanan lagi.');

        // Honeypot: kolom tersembunyi yang hanya diisi bot. Pura-pura berhasil
        // supaya bot tidak belajar bahwa ia ketahuan.
        if (filled($request->input('situs'))) {
            $request->session()->forget(self::SESI_LOLOS);
            $request->session()->flash('izin_terkirim', ['nama' => 'anak Anda', 'sekolah' => 'sekolah']);

            return to_route('izin.terkirim');
        }

        if (now()->timestamp - (int) $request->session()->get(self::SESI_LOLOS_PADA, 0) < self::MIN_DETIK_ISI) {
            throw ValidationException::withMessages(['alasan' => 'Pengajuan terkirim terlalu cepat. Periksa lagi isian lalu kirim ulang.']);
        }

        $data = $request->validate(PerizinanService::aturan() + [
            'school_id' => ['required', 'string'],
            'student_id' => ['required', 'string'],
            'pengirim_nama' => ['required', 'string', 'max:100'],
            'pengirim_telepon' => ['required', 'string', 'regex:/^\+?[0-9]{9,15}$/'],
        ], PerizinanService::pesan() + [
            'pengirim_nama.required' => 'Tuliskan nama Anda.',
            'pengirim_telepon.regex' => 'Nomor WhatsApp hanya angka, 9–15 digit.',
        ]);

        $sekolah = $this->sekolahAktif()->firstWhere('id', $data['school_id']);
        $siswa = $sekolah ? Student::withoutGlobalScope('school')
            ->where('school_id', $sekolah->id)
            ->where('is_active', true)
            ->find($data['student_id']) : null;

        if (! $siswa) {
            throw ValidationException::withMessages(['student_id' => 'Siswa tidak ditemukan di sekolah yang dipilih. Cari ulang nama anak.']);
        }

        $hariIni = PengajuanIzin::withoutGlobalScope('school')
            ->where('student_id', $siswa->id)
            ->where('sumber', PengajuanIzin::SUMBER_PUBLIK)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
        if ($hariIni >= self::MAKS_PER_SISWA_HARIAN) {
            throw ValidationException::withMessages(['student_id' => 'Pengajuan untuk anak ini hari ini sudah banyak. Hubungi wali kelas bila perlu perubahan.']);
        }

        $izin = $perizinan->ajukan($siswa, $data, PengajuanIzin::SUMBER_PUBLIK, $request->file('bukti'));
        $request->session()->forget(self::SESI_LOLOS);
        $request->session()->flash('izin_terkirim', ['nama' => $siswa->full_name, 'sekolah' => $sekolah->name, 'id' => $izin->id]);

        return to_route('izin.terkirim');
    }

    public function terkirim(Request $request): Response|RedirectResponse
    {
        $info = $request->session()->get('izin_terkirim');

        return $info ? Inertia::render('public/izin-terkirim', ['info' => $info]) : to_route('izin.index');
    }

    /**
     * Sekolah aktif yang memakai absensi sekolah. `settings` berisi kredensial,
     * jadi hanya id/nama/kota yang dikirim ke halaman.
     *
     * @return Collection<int, School>
     */
    private function sekolahAktif(): Collection
    {
        return School::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'city', 'settings'])
            ->filter(fn (School $s) => SchoolFeatures::for($s)->enabled(SchoolFeature::AbsensiSekolah))
            ->values();
    }

    private function soalBaru(Request $request): string
    {
        $a = random_int(2, 9);
        $b = random_int(1, 9);
        $request->session()->put(self::SESI_JAWABAN, $a + $b);

        return "{$a} + {$b}";
    }

    private function lolos(Request $request): bool
    {
        return (int) $request->session()->get(self::SESI_LOLOS, 0) > now()->timestamp;
    }
}
