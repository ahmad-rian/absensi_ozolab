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

        $flags = SchoolFeatures::for($school);

        foreach ($features as $value) {
            $feature = SchoolFeature::tryFrom($value);

            if ($feature === null) {
                // Salah ketik di routes/web.php harus berisik, bukan diam-diam
                // membuka pintu.
                abort(500, "Fitur tidak dikenal: {$value}");
            }

            if ($flags->enabled($feature)) {
                return $next($request);
            }
        }

        abort(403, 'Fitur ini sedang dimatikan untuk sekolah Anda.');
    }

    private function resolveSchool(Request $request): ?School
    {
        // Rute publik mengikat {school:scanner_token}; rute admin memakai
        // singleton yang dipasang SetCurrentSchool.
        $bound = $request->route('school');

        if ($bound instanceof School) {
            return $bound;
        }

        return app()->bound('currentSchool') ? app('currentSchool') : null;
    }
}
