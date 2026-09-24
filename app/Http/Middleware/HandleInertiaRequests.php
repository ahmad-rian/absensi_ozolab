<?php

namespace App\Http\Middleware;

use App\Models\NotificationLog;
use App\Models\School;
use App\Models\Setting;
use App\Models\User;
use App\Support\SchoolFeatures;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar_path,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ] : null,
            ],
            'impersonation' => function () use ($request) {
                $originalId = $request->session()->get('impersonator_id');

                if (! $originalId) {
                    return ['active' => false, 'original_name' => null];
                }

                return [
                    'active' => true,
                    'original_name' => User::find($originalId)?->name,
                ];
            },
            // Dipakai badge di sidebar. Closure, jadi query-nya hanya jalan
            // saat prop ini benar-benar diminta.
            'notifications' => $user ? function () use ($user) {
                return [
                    'unread' => $user->school_id
                        ? NotificationLog::where('school_id', $user->school_id)->whereNull('read_at')->count()
                        : 0,
                ];
            } : ['unread' => 0],
            // Peta lengkap nyala/mati fitur sekolah aktif. Selalu utuh (semua
            // case hadir), jadi frontend boleh memakai perbandingan ketat dan
            // tidak perlu menebak makna key yang hilang.
            'features' => function () {
                $school = app()->bound('currentSchool') ? app('currentSchool') : null;

                return SchoolFeatures::for($school)->toArray();
            },
            'currentSchool' => function () {
                // Read from singleton set by SetCurrentSchool middleware — always fresh
                $school = app()->bound('currentSchool') ? app('currentSchool') : null;

                return $school ? [
                    'id' => $school->id,
                    'name' => $school->name,
                    'slug' => $school->slug,
                ] : null;
            },
            // Daftar sekolah hanya relevan untuk school switcher SUPER_ADMIN;
            // mengirimnya ke semua user membocorkan daftar tenant.
            'schools' => $user?->isSuperAdmin() ? function () {
                return School::where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug']);
            } : [],
            'app' => function () use ($user) {
                // Branding milik sekolah aktif, dengan fallback ke nilai global
                // lama supaya tampilan tidak berubah setelah deploy.
                $school = app()->bound('currentSchool') ? app('currentSchool') : null;

                // Sekolah milik akun yang sedang masuk, untuk halaman di luar
                // grup yang memasang SetCurrentSchool — ganti-password salah
                // satunya. Tanpa ini, orang yang baru login melihat logo
                // Laravel bawaan padahal logonya sudah diunggah; logo itu
                // tersimpan di kolom settings sekolah, bukan di tabel settings
                // global, jadi jalur fallback lama tidak pernah menemukannya.
                if (! $school && $user?->school_id) {
                    $school = School::where('is_active', true)->find($user->school_id);
                }

                if (! $school) {
                    $publicSchoolId = Setting::getValue('public_branding_school_id');
                    $school = $publicSchoolId ? School::where('is_active', true)->find($publicSchoolId) : null;
                }

                $logoPath = $school?->getSetting('app_logo') ?: Setting::getValue('app_logo');
                $faviconPath = $school?->getSetting('app_favicon') ?: Setting::getValue('app_favicon');

                return [
                    'school_name' => $school?->name ?? Setting::getValue('school_name', 'SMP Nusantara'),
                    'logo' => $logoPath ? Storage::disk('public')->url($logoPath) : null,
                    'favicon' => $faviconPath ? Storage::disk('public')->url($faviconPath) : null,
                ];
            },
            // Alamat Tyas Studio, aplikasi jepret + crop di subdomain sendiri.
            // Dibagikan supaya sidebar dan tombol di halaman siswa tidak
            // menuliskan domainnya sendiri-sendiri.
            'studioUrl' => config('services.studio.url'),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'info' => $request->session()->get('info'),
                'warning' => $request->session()->get('warning'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
