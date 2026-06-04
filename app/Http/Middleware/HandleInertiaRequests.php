<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Models\Setting;
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
            'currentSchool' => function () {
                // Read from singleton set by SetCurrentSchool middleware — always fresh
                $school = app()->bound('currentSchool') ? app('currentSchool') : null;

                return $school ? [
                    'id' => $school->id,
                    'name' => $school->name,
                    'slug' => $school->slug,
                ] : null;
            },
            'schools' => $user ? function () {
                return School::where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug']);
            } : [],
            'app' => function () {
                $logoPath = Setting::getValue('app_logo');
                $faviconPath = Setting::getValue('app_favicon');

                return [
                    'school_name' => Setting::getValue('school_name', 'SMP Nusantara'),
                    'logo' => $logoPath ? Storage::disk('public')->url($logoPath) : null,
                    'favicon' => $faviconPath ? Storage::disk('public')->url($faviconPath) : null,
                ];
            },
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
