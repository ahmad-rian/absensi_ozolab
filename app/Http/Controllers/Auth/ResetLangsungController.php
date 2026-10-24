<?php

namespace App\Http\Controllers\Auth;

use App\Console\Commands\NotifyAttendanceAbsenceCommand;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ParentProfile;
use App\Models\User;
use App\Rules\SandiUmum;
use App\Support\AturanSandi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Lupa sandi orang tua tanpa membuka email: email login + nomor WhatsApp yang
 * terdaftar di sekolah + captcha, lalu sandi baru langsung di halaman yang sama.
 *
 * Keputusan pemilik (pengguna awam teknologi). Batasnya:
 * - HANYA akun orang tua murni. Admin, super admin, guru, staf General, dan
 *   akun penyelenggara tetap lewat link email / direset admin — mengambil alih
 *   salah satunya membuka data seluruh siswa.
 * - Nomor WhatsApp harus cocok dengan profil orang tua akun itu, jadi tahu
 *   email saja tidak cukup.
 * - Captcha di server, kunci setelah salah berulang per IP dan per akun, dan
 *   pesan gagal sama untuk semua sebab supaya tidak membocorkan akun mana ada.
 */
class ResetLangsungController extends Controller
{
    public const SESI_CAPTCHA = 'lupa_sandi_captcha';

    /** Bag galat terpisah: halaman yang sama juga punya form link email. */
    public const BAG = 'langsung';

    /** Percobaan gagal (captcha/identitas) sebelum dikunci. */
    private const MAKS_GAGAL = 5;

    /** Lama kunci setelah terlalu banyak gagal, dalam detik. */
    private const DETIK_KUNCI = 900;

    private const PESAN_TIDAK_COCOK = 'Email dan nomor WhatsApp tidak cocok dengan akun orang tua mana pun. Periksa lagi, atau minta admin sekolah mereset sandi.';

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag(self::BAG, [
            'email' => ['required', 'string', 'email', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:20'],
            'captcha' => ['required', 'integer'],
            'password' => ['required', 'string', 'confirmed', AturanSandi::longgar(), new SandiUmum([$request->input('whatsapp'), Str::before((string) $request->input('email'), '@')])],
        ], [
            'whatsapp.required' => 'Isi nomor WhatsApp yang terdaftar di sekolah.',
            'captcha.required' => 'Jawab soal hitungan dulu.',
            'password.confirmed' => 'Ulangi sandi belum sama.',
        ]);

        $kunciIp = 'lupa-sandi-ip:'.$request->ip();
        $kunciAkun = 'lupa-sandi-akun:'.Str::lower($data['email']);

        foreach ([$kunciIp, $kunciAkun] as $kunci) {
            if (RateLimiter::tooManyAttempts($kunci, self::MAKS_GAGAL)) {
                $menit = (int) ceil(RateLimiter::availableIn($kunci) / 60);

                throw ValidationException::withMessages(['email' => "Terlalu banyak percobaan. Coba lagi {$menit} menit lagi."])->errorBag(self::BAG);
            }
        }

        $jawaban = $request->session()->pull(self::SESI_CAPTCHA);

        if ($jawaban === null || (int) $data['captcha'] !== (int) $jawaban) {
            RateLimiter::hit($kunciIp, self::DETIK_KUNCI);

            throw ValidationException::withMessages(['captcha' => 'Jawaban hitungan belum tepat. Coba soal yang baru.'])->errorBag(self::BAG);
        }

        $akun = $this->akunOrangTua($data['email'], $data['whatsapp']);

        if ($akun === null) {
            RateLimiter::hit($kunciIp, self::DETIK_KUNCI);
            RateLimiter::hit($kunciAkun, self::DETIK_KUNCI);

            throw ValidationException::withMessages(['email' => self::PESAN_TIDAK_COCOK])->errorBag(self::BAG);
        }

        RateLimiter::clear($kunciIp);
        RateLimiter::clear($kunciAkun);

        $akun->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
            // Sesi "ingat saya" lama di perangkat lain ikut gugur.
            'remember_token' => Str::random(60),
        ])->save();

        activity()->performedOn($akun)->causedBy($akun)
            ->withProperties(['ip' => $request->ip(), 'jalur' => 'lupa-sandi-langsung'])
            ->log('sandi direset tanpa email');

        return to_route('login')->with('status', 'Sandi berhasil diganti. Silakan masuk dengan sandi baru.');
    }

    /**
     * Soal captcha baru untuk halaman lupa sandi (disimpan di session).
     */
    public static function soalBaru(Request $request): string
    {
        $a = random_int(2, 9);
        $b = random_int(1, 9);
        $request->session()->put(self::SESI_CAPTCHA, $a + $b);

        return "{$a} + {$b}";
    }

    /**
     * Akun orang tua murni yang email-nya cocok dan salah satu profil orang
     * tuanya memakai nomor WhatsApp itu. Akun berperan lain selalu null.
     */
    private function akunOrangTua(string $email, string $whatsapp): ?User
    {
        $akun = User::query()->where('email', Str::lower(trim($email)))->first();

        if ($akun === null || ! $akun->is_active || ! $akun->hasRole(UserRole::OrangTua->value)) {
            return null;
        }

        $berperanLain = $akun->getRoleNames()->contains(fn (string $peran) => $peran !== UserRole::OrangTua->value);

        if ($berperanLain || $akun->penyelenggara_id !== null) {
            return null;
        }

        $nomor = NotifyAttendanceAbsenceCommand::nomorSeragam($whatsapp);

        if (strlen($nomor) < 8) {
            return null;
        }

        $cocok = ParentProfile::withoutGlobalScopes()
            ->where('user_id', $akun->id)
            ->pluck('whatsapp_number')
            ->contains(fn (?string $n) => $n !== null && NotifyAttendanceAbsenceCommand::nomorSeragam($n) === $nomor);

        return $cocok ? $akun : null;
    }
}
