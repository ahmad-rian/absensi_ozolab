<?php

namespace App\Http\Middleware;

use App\Models\School;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentSchool
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Hanya SUPER_ADMIN yang boleh berpindah sekolah lewat session. Untuk
        // role lain konteksnya selalu users.school_id, sehingga nilai session
        // sisa (mis. setelah impersonate) tidak bisa membawa mereka ke sekolah
        // lain.
        $schoolId = $user->isSuperAdmin()
            ? session('current_school_id', $user->school_id)
            : $user->school_id;

        $school = $schoolId
            ? School::where('id', $schoolId)->where('is_active', true)->first()
            : null;

        if (! $school) {
            $request->session()->forget('current_school_id');

            return $next($request);
        }

        session(['current_school_id' => $school->id]);

        // Kolom users.school_id ikut disinkronkan hanya untuk SUPER_ADMIN,
        // karena global scope sekolah membacanya.
        if ($user->isSuperAdmin() && $user->school_id !== $school->id) {
            $user->school_id = $school->id;
            $user->saveQuietly(); // no events, just sync
        }

        app()->instance('currentSchool', $school);

        return $next($request);
    }
}
