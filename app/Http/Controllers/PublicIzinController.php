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
        $jawaban = $request->validate(['jawaban' => ['required', 'integer']])['jawaban'];
        $benar = $request->session()->pull(self::SESI_JAWABAN);

        if ($benar === null || (int) $jawaban !== (int) $benar) {
            return response()->json([
                'message' => 'Jawaban belum tepat. Coba soal yang baru.',
                'soal' => $this->soalBaru($request),
            ], 422);
        }

        $request->session()->put(self::SESI_LOLOS, now()->addMinutes(self::MENIT_LOLOS)->timestamp);

        return response()->json(['ok' => true]);
    }

    public function siswa(Request $request): JsonResponse
    {
        abort_unless($this->lolos($request), 403, 'Selesaikan pertanyaan keamanan dulu.');

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
