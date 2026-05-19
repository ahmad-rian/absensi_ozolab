<?php

namespace App\Http\Middleware;

use App\Models\School;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentSchool
{
    /**
     * Atur sekolah aktif berdasarkan user yang sedang login.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->school_id) {
            $school = School::find($user->school_id);

            // Simpan sekolah aktif di singleton agar bisa diakses di seluruh aplikasi
            app()->instance('currentSchool', $school);

            // Bagikan data sekolah ke Inertia
            Inertia::share('currentSchool', fn () => $school ? [
                'id' => $school->id,
                'name' => $school->name,
                'slug' => $school->slug,
                'logo_path' => $school->logo_path,
                'favicon_path' => $school->favicon_path,
            ] : null);
        }

        return $next($request);
    }
}
