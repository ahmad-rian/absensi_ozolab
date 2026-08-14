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

        // Global scope sekolah membaca users.school_id, jadi nilainya diselaraskan
        // untuk SUPER_ADMIN — tapi HANYA di memori, tidak disimpan.
        //
        // Sekolah yang sedang dibuka adalah pilihan per-browser dan tempatnya di
        // session. Menuliskannya ke kolom akun membuat dua browser pada satu akun
        // saling menggeser konteks tiap request, dan yang kalah balapan mendapat
        // 404 atas datanya sendiri. Kolomnya tetap dipakai sebagai sekolah asal,
        // yaitu nilai awal ketika session belum punya pilihan.
        if ($user->isSuperAdmin() && $user->school_id !== $school->id) {
            $user->school_id = $school->id;
        }

        app()->instance('currentSchool', $school);

        return $next($request);
    }
}
