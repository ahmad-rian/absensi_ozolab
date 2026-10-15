<?php

namespace App\Providers;

use App\Events\StudentCheckedIn;
use App\Events\StudentCheckedOut;
use App\Listeners\DispatchAttendanceNotifications;
use App\Listeners\LogAttendanceActivity;
use App\Models\User;
use App\Support\AturanSandi;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        $this->configureDefaults();
        $this->configureEvents();
        $this->configureAuthorization();
        $this->configureRateLimiting();
    }

    /**
     * Batas scan gerbang dihitung per SEKOLAH, bukan per alamat IP.
     *
     * Limiter bawaan `throttle:120,1` berkunci IP untuk pengunjung anonim, dan
     * satu sekolah keluar lewat satu IP publik. Tiga gerbang pada jam
     * kedatangan pagi melewati 120 scan per menit dengan mudah, dan yang
     * terjadi adalah 429 — di layar gerbang itu tampak persis seperti
     * "kadang berhenti merespons", tanpa satu pun petunjuk kenapa.
     *
     * Pola yang sama pernah menggigit dari arah lain: `auto_ban.sh` dulu
     * menganggap satu sekolah yang mendaftarkan ratusan siswa sebagai DDoS.
     * Satuan yang benar untuk aplikasi ini memang sekolah, bukan IP.
     *
     * Batasnya tetap ada — endpoint ini publik. Yang berhenti adalah membagi
     * satu kuota dengan seluruh perangkat di gedung yang sama. Kunci cadangan
     * ke IP menjaga permintaan yang sekolahnya tidak terbaca tetap terbatas.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('scan-gerbang', function (Request $request) {
            /*
                Parameter rutenya masih berupa STRING di sini, bukan model.

                `ThrottleRequests` berada di depan `SubstituteBindings` pada
                daftar prioritas middleware, jadi limiter berjalan sebelum
                binding sempat mengubah token jadi objek School. Versi pertama
                menulis `->route('school')?->id`, yang pada string menghasilkan
                null tanpa error — lalu diam-diam jatuh ke kunci IP, yaitu
                persis perilaku yang hendak dibuang. Tesnya yang menangkap.

                Token itu sendiri sudah mengidentifikasi sekolah secara unik,
                jadi memakainya apa adanya sebagai kunci sudah benar.
            */
            $sekolah = $request->route('school') ?? $request->route('kode');
            $kunci = is_object($sekolah) ? $sekolah->id : $sekolah;

            return Limit::perMinute(600)->by('scan-gerbang:'.($kunci ?: $request->ip()));
        });

        /*
            Form izin publik: dibatasi per IP DAN per sesi. Per IP menahan
            satu mesin yang membanjiri; per sesi menahan satu peramban yang
            berganti-ganti IP. Batas per IP sengaja longgar karena orang tua
            bisa berbagi IP lewat wifi sekolah atau operator seluler (CGNAT).
        */
        RateLimiter::for('izin-publik', fn (Request $request) => [
            Limit::perMinute(60)->by('izin-ip:'.$request->ip()),
            Limit::perHour(600)->by('izin-ip-jam:'.$request->ip()),
            Limit::perMinute(30)->by('izin-sesi:'.$request->session()->getId()),
        ]);
        RateLimiter::for('izin-verifikasi', fn (Request $request) => [
            Limit::perMinute(15)->by('izin-verif-ip:'.$request->ip()),
            Limit::perMinute(6)->by('izin-verif-sesi:'.$request->session()->getId()),
        ]);
        RateLimiter::for('izin-kirim', fn (Request $request) => [
            Limit::perMinute(10)->by('izin-kirim-ip:'.$request->ip()),
            Limit::perDay(100)->by('izin-kirim-ip-hari:'.$request->ip()),
            Limit::perMinute(3)->by('izin-kirim-sesi:'.$request->session()->getId()),
        ]);
    }

    /**
     * SUPER_ADMIN lolos semua gate, jadi tidak perlu sinkron ulang tiap kali
     * modul baru ditambahkan — dan tidak mungkin mengunci dirinya sendiri.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);
    }

    protected function configureEvents(): void
    {
        Event::listen(StudentCheckedIn::class, DispatchAttendanceNotifications::class);
        Event::listen(StudentCheckedOut::class, DispatchAttendanceNotifications::class);
        Event::listen(StudentCheckedIn::class, LogAttendanceActivity::class);
        Event::listen(StudentCheckedOut::class, LogAttendanceActivity::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Definisinya di AturanSandi, bukan di sini: halaman ganti sandi
        // memajang syaratnya sebagai daftar centang, dan daftar itu harus
        // dibangkitkan dari aturan yang sama persis dengan yang menolak.
        Password::defaults(fn (): Password => AturanSandi::bawaan());
    }
}
