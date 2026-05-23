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
                'last_tested_at' => $config->last_tested_at?->format('d M Y H:i'),
            ] : null,
            'hasGlobalCredentials' => GoogleDriveService::hasGlobalCredentials(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'root_folder_id' => ['nullable', 'string', 'max:255'],
            'cards_folder_id' => ['nullable', 'string', 'max:255'],
            'albums_folder_id' => ['nullable', 'string', 'max:255'],
            'parents_folder_id' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $school = School::findOrFail(auth()->user()->school_id);
        $config = $school->driveConfig ?? new SchoolDriveConfig(['school_id' => $school->id]);

        $config->root_folder_id = $validated['root_folder_id'] ?? $config->root_folder_id;
        $config->cards_folder_id = $validated['cards_folder_id'] ?? $config->cards_folder_id;
        $config->albums_folder_id = $validated['albums_folder_id'] ?? $config->albums_folder_id;
        $config->parents_folder_id = $validated['parents_folder_id'] ?? $config->parents_folder_id;
        $config->is_active = $validated['is_active'] ?? false;
        $config->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Konfigurasi folder Google Drive berhasil disimpan.']);

        return to_route('admin.drive-config');
    }

    public function test(): RedirectResponse
    {
        $school = School::findOrFail(auth()->user()->school_id);
        $config = $school->driveConfig;

        if (! $config) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Konfigurasi folder belum diisi.']);

            return to_route('admin.drive-config');
        }

        if (! GoogleDriveService::hasGlobalCredentials() && ! $config->service_account_json) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Credential Google Drive belum dikonfigurasi oleh Super Admin.']);

            return to_route('admin.drive-config');
        }

        try {
            $service = GoogleDriveService::forSchool($config);
            $success = $service->testConnection();

            $config->update(['last_tested_at' => now()]);

            if ($success) {
                Inertia::flash('toast', ['type' => 'success', 'message' => 'Koneksi Google Drive berhasil!']);
            } else {
                Inertia::flash('toast', ['type' => 'error', 'message' => 'Koneksi gagal. Periksa folder ID dan pastikan folder di-share ke service account.']);
            }
        } catch (\Throwable $e) {
            Log::error('Google Drive test failed', ['error' => $e->getMessage()]);
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Error: '.$e->getMessage()]);
        }

        return to_route('admin.drive-config');
    }
}
