<?php

namespace App\Http\Middleware;

use App\Enums\SchoolFeature;
use App\Models\School;
use App\Support\SchoolFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tolak rute yang fiturnya dimatikan sekolah aktif.
 *
 * Berbeda dari `permission:` — ini bukan keputusan otorisasi, melainkan status
 * langganan tenant. Karena itu `Gate::before` milik SUPER_ADMIN sengaja TIDAK
 * berlaku di sini: super admin yang sedang membuka Sekolah X harus melihat
 * Sekolah X persis seperti penggunanya, kalau tidak setiap laporan "menu saya
 * hilang tapi punya Anda ada" jadi mustahil direproduksi.
 *
 * Beberapa nama fitur berarti OR — cukup satu aktif untuk lolos.
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        $school = $this->resolveSchool($request);

        // Tanpa konteks sekolah (super admin belum memilih tenant) tidak ada
        // yang bisa dinilai — biarkan lewat, global scope yang menjaga data.
        if (! $school) {
            return $next($request);
        }

        // SELURUH nama divalidasi lebih dulu. Kalau validasinya menumpang di
        // dalam loop pencocokan, salah ketik pada nama kedua tidak pernah
        // terdeteksi selama nama pertama kebetulan aktif.
        $resolved = array_map(function (string $value) {
            $feature = SchoolFeature::tryFrom($value);

            // Salah ketik di routes/web.php harus berisik, bukan diam-diam
            // membuka pintu.
            abort_if($feature === null, 500, "Fitur tidak dikenal: {$value}");

            return $feature;
        }, $features);

        $flags = SchoolFeatures::for($school);

        foreach ($resolved as $feature) {
            if ($flags->enabled($feature)) {
                return $next($request);
            }
        }

        abort(403, 'Fitur ini sedang dimatikan untuk sekolah Anda.');
    }

    private function resolveSchool(Request $request): ?School
    {
        // Sekolah pengguna didahulukan. Memakai binding rute lebih dulu membuat
        // `/api/schools/{lain}/students` dinilai atas fitur milik tenant LAIN —
        // selisih 403/404-nya jadi oracle yang membocorkan status fitur mereka,
        // dan pesan galatnya pun menyesatkan.
        if (app()->bound('currentSchool')) {
            return app('currentSchool');
        }

        // Rute publik (scan) tidak punya pengguna, jadi di sanalah binding
        // {school:scanner_token} memang satu-satunya konteks yang ada.
        $bound = $request->route('school');

        return $bound instanceof School ? $bound : null;
    }
}
