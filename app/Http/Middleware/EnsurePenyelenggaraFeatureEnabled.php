<?php

namespace App\Http\Middleware;

use App\Enums\PenyelenggaraFeature;
use App\Support\PenyelenggaraFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tolak rute yang fiturnya dimatikan untuk penyelenggara aktif.
 *
 * Super admin TIDAK menembus — ia harus melihat penyelenggara persis seperti
 * penggunanya (pola EnsureFeatureEnabled).
 */
class EnsurePenyelenggaraFeatureEnabled
{
    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        $resolved = array_map(function (string $value) {
            $feature = PenyelenggaraFeature::tryFrom($value);
            abort_if($feature === null, 500, "Fitur penyelenggara tidak dikenal: {$value}");

            return $feature;
        }, $features);

        $penyelenggara = app()->bound('currentPenyelenggara') ? app('currentPenyelenggara') : null;

        if (! $penyelenggara) {
            return $next($request);
        }

        $flags = PenyelenggaraFeatures::for($penyelenggara);

        foreach ($resolved as $feature) {
            if ($flags->enabled($feature)) {
                return $next($request);
            }
        }

        abort(403, 'Fitur ini sedang dimatikan untuk penyelenggara Anda.');
    }
}
