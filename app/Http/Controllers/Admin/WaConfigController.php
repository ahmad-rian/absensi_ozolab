<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolWaConfig;
use App\Services\Notification\WhatsAppGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WaConfigController extends Controller
{
    public function index(): Response
    {
        $schoolId = auth()->user()->school_id;
        $config = SchoolWaConfig::where('school_id', $schoolId)->first();

        return Inertia::render('admin/wa-config/index', [
            'config' => $config ? [
                'id' => $config->id,
                'display_phone' => $config->display_phone,
                'is_active' => $config->is_active,
                'has_token' => ! empty($config->fonnte_token),
            ] : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'fonnte_token' => ['required', 'string', 'min:10'],
            'display_phone' => ['required', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ]);

        $schoolId = auth()->user()->school_id;

        SchoolWaConfig::updateOrCreate(
            ['school_id' => $schoolId],
            [
                'fonnte_token' => $validated['fonnte_token'],
                'display_phone' => $validated['display_phone'],
                'is_active' => $validated['is_active'] ?? false,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Konfigurasi WhatsApp berhasil disimpan.']);

        return to_route('admin.wa-config');
    }

    public function testMessage(Request $request, WhatsAppGateway $gateway): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'min:10'],
        ]);

        $schoolId = auth()->user()->school_id;

        $success = $gateway->sendText(
            $request->phone,
            'Test notifikasi dari sistem absensi. Jika Anda menerima pesan ini, konfigurasi WhatsApp berhasil!',
            $schoolId,
        );

        return response()->json([
            'success' => $success,
            'message' => $success
                ? 'Pesan test berhasil dikirim!'
                : 'Gagal mengirim pesan. Periksa token Fonnte dan pastikan device aktif.',
        ]);
    }

    public function destroy(): RedirectResponse
    {
        $schoolId = auth()->user()->school_id;

        SchoolWaConfig::where('school_id', $schoolId)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Konfigurasi WhatsApp berhasil dihapus.']);

        return to_route('admin.wa-config');
    }
}
