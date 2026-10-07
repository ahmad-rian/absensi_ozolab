<?php

namespace App\Http\Controllers;

use App\Models\CardGenerationLog;
use App\Services\Berkas\BerkasKeluaran;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Kartu dan lembar pas foto lewat URL bertanda tangan. Tanda tangannya yang
 * memberi izin — URL ini dibuat oleh halaman admin, portal orang tua, dan
 * hasil pendaftaran yang sudah memeriksa haknya masing-masing.
 */
class BerkasKeluaranController extends Controller
{
    public function __invoke(Request $request, string $log, BerkasKeluaran $berkas): BinaryFileResponse
    {
        $catatan = CardGenerationLog::withoutGlobalScopes()->findOrFail($log);

        return $berkas->respons($catatan, $request->boolean('unduh'));
    }
}
