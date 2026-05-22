<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolDriveConfig;
use App\Services\GoogleDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class DriveConfigController extends Controller
{
    public function index(): Response
    {
        $school = School::findOrFail(auth()->user()->school_id);
        $config = $school->driveConfig;

        return Inertia::render('admin/drive-config/index', [
            'driveConfig' => $config ? [
                'id' => $config->id,
                'root_folder_id' => $config->root_folder_id,
                'cards_folder_id' => $config->cards_folder_id,
                'albums_folder_id' => $config->albums_folder_id,
                'parents_folder_id' => $config->parents_folder_id,
                'is_active' => $config->is_active,
                'has_credentials' => (bool) $config->service_account_json,
                'last_tested_at' => $config->last_tested_at?->format('d M Y H:i'),
            ] : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_account_json' => ['nullable', 'string'],
            'root_folder_id' => ['nullable', 'string', 'max:255'],
            'cards_folder_id' => ['nullable', 'string', 'max:255'],
            'albums_folder_id' => ['nullable', 'string', 'max:255'],
            'parents_folder_id' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $school = School::findOrFail(auth()->user()->school_id);

        // Validate JSON if provided
        if (! empty($validated['service_account_json'])) {
            $decoded = json_decode($validated['service_account_json'], true);
            if (! is_array($decoded) || ! isset($decoded['type']) || $decoded['type'] !== 'service_account') {
                return back()->withErrors(['service_account_json' => 'JSON harus berupa Service Account credential dari Google Cloud.']);
            }
        }

        $config = $school->driveConfig ?? new SchoolDriveConfig(['school_id' => $school->id]);

        if (! empty($validated['service_account_json'])) {
            $config->service_account_json = $validated['service_account_json'];
        }

        $config->root_folder_id = $validated['root_folder_id'] ?? $config->root_folder_id;
        $config->cards_folder_id = $validated['cards_folder_id'] ?? $config->cards_folder_id;
        $config->albums_folder_id = $validated['albums_folder_id'] ?? $config->albums_folder_id;
        $config->parents_folder_id = $validated['parents_folder_id'] ?? $config->parents_folder_id;
        $config->is_active = $validated['is_active'] ?? false;
        $config->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Konfigurasi Google Drive berhasil disimpan.']);

        return to_route('admin.drive-config');
    }

    public function test(): RedirectResponse
    {
        $school = School::findOrFail(auth()->user()->school_id);
        $config = $school->driveConfig;

        if (! $config || ! $config->service_account_json) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Credential belum dikonfigurasi.']);

            return to_route('admin.drive-config');
        }

        try {
            $service = GoogleDriveService::forSchool($config);
            $success = $service->testConnection();

            $config->update(['last_tested_at' => now()]);

            if ($success) {
                // Auto-create subfolders if root is set
                if ($config->root_folder_id) {
                    $service->ensureSubfolders();
                }

                Inertia::flash('toast', ['type' => 'success', 'message' => 'Koneksi Google Drive berhasil! Folder siap digunakan.']);
            } else {
                Inertia::flash('toast', ['type' => 'error', 'message' => 'Koneksi gagal. Periksa credential dan folder ID.']);
            }
        } catch (\Throwable $e) {
            Log::error('Google Drive test failed', ['error' => $e->getMessage()]);
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Error: '.$e->getMessage()]);
        }

        return to_route('admin.drive-config');
    }
}
