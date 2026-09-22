<?php

namespace App\Http\Middleware;

use App\Models\ParentProfile;
use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menetapkan anak yang sedang dilihat, dan menolak anak milik orang lain.
 *
 * Anaknya datang dari `?anak=`; tanpa itu dipakai anak pertama. Hasilnya
 * ditaruh di `$request->attributes` — BUKAN lewat route model binding, karena
 * binding menerapkan global scope `school` yang menilai konteks sekolah
 * pengguna dan akan 404 sebelum controller sempat jalan.
 */
class EnsureAnakSendiri
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->validate(['anak' => ['nullable', 'string']]);
        $diminta = $request->query('anak') ?: $request->route('anak');
        $parent = $request->user()?->parentProfile;

        // Orang tua tanpa anak tertaut tetap boleh masuk; halamannya yang
        // menjelaskan keadaan itu, bukan layar 403 tanpa keterangan.
        if (! $parent instanceof ParentProfile) {
            abort_if($diminta, 403);
            $request->attributes->set('portalStudent', null);
            $request->attributes->set('portalChildren', collect());

            return $next($request);
        }

        $children = Student::where('parent_profile_id', $parent->id)
            ->where('school_id', $parent->school_id)
            ->with(['classroom', 'school'])
            ->orderBy('full_name')
            ->get();

        $student = $diminta ? $children->firstWhere('id', $diminta) : $children->first();

        // Id yang disebut tapi bukan anaknya adalah percobaan menembus, bukan
        // salah ketik — jangan diam-diam dialihkan ke anak sendiri.
        abort_if($diminta && ! $student, 403);

        $request->attributes->set('portalStudent', $student);
        $request->attributes->set('portalChildren', $children);

        return $next($request);
    }
}
