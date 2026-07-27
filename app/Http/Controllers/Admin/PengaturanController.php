<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Setting;
use App\Services\ImageConverter;
use App\Support\PrayerSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PengaturanController extends Controller
{
    public function index(): Response
    {
        $school = School::find(auth()->user()->school_id);

        $keys = [
            'school_name',
            'school_logo',
            'school_favicon',
            'timezone',
            'prayer_enabled',
            'prayer_start',
            'prayer_end',
            'prayer_all_religions',
            'whatsapp_enabled',
            'notify_on_check_in',
            'notify_on_check_out',
            'whatsapp_template_attendance',
        ];

        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = $school?->getSetting($key) ?? Setting::getValue($key, '');
        }

        if ($school) {
            $settings['school_name'] = $school->getSetting('school_name') ?? $school->name;

            // Jam sholat jatuh ke default config kalau sekolah belum pernah
            // menyimpannya, supaya form tidak tampil kosong.
            $prayer = PrayerSettings::for($school);
            $settings['prayer_enabled'] = $prayer->enabled;
            $settings['prayer_start'] = $prayer->displayStart();
            $settings['prayer_end'] = $prayer->displayEnd();
            $settings['prayer_all_religions'] = $prayer->allReligions;
        }

        // Branding per sekolah, dengan fallback ke nilai global lama supaya
        // sekolah yang belum pernah mengunggah tidak kehilangan logonya.
        $logoPath = $school?->getSetting('app_logo') ?: Setting::getValue('app_logo', '');
        $faviconPath = $school?->getSetting('app_favicon') ?: Setting::getValue('app_favicon', '');

        return Inertia::render('admin/pengaturan/index', [
            'settings' => $settings,
            'logoUrl' => $logoPath && Storage::disk('public')->exists($logoPath)
                ? Storage::disk('public')->url($logoPath)
                : '',
            'faviconUrl' => $faviconPath && Storage::disk('public')->exists($faviconPath)
                ? Storage::disk('public')->url($faviconPath)
                : '',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'school_name' => ['nullable', 'string', 'max:255'],
            // Jam masuk/pulang tidak diatur di sini — sumber kebenarannya
            // adalah menu Jadwal Absensi (tabel attendance_schedules).
            'timezone' => ['nullable', 'string', 'in:Asia/Jakarta,Asia/Makassar,Asia/Jayapura'],
            'prayer_enabled' => ['nullable', 'boolean'],
            'prayer_start' => ['nullable', 'date_format:H:i'],
            'prayer_end' => ['nullable', 'date_format:H:i', 'after:prayer_start'],
            'prayer_all_religions' => ['nullable', 'boolean'],
            'whatsapp_enabled' => ['nullable'],
            'notify_on_check_in' => ['nullable'],
            'notify_on_check_out' => ['nullable'],
            'whatsapp_template_attendance' => ['nullable', 'string', 'max:2000'],
        ]);

        $school = School::find(auth()->user()->school_id);

        if ($school) {
            $settings = $school->settings ?? [];
            foreach ($validated as $key => $value) {
                $settings[$key] = $value;
            }
            $school->settings = $settings;

            if (isset($validated['school_name']) && $validated['school_name']) {
                $school->name = $validated['school_name'];
            }

            $school->save();
        } else {
            foreach ($validated as $key => $value) {
                Setting::setValue($key, $value);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengaturan berhasil disimpan.']);

        return to_route('admin.pengaturan');
    }

    public function uploadLogo(Request $request, ImageConverter $converter): RedirectResponse
    {
        $request->validate(['logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);

        $path = $converter->storeAsWebp($request->file('logo'), 'images/branding', 'public', 85, 512);
        $this->storeBranding('app_logo', $path);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Logo berhasil diupload.']);

        return to_route('admin.pengaturan');
    }

    public function uploadFavicon(Request $request, ImageConverter $converter): RedirectResponse
    {
        $request->validate(['favicon' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,ico', 'max:2048']]);

        $path = $converter->storeAsWebp($request->file('favicon'), 'images/branding', 'public', 85, 128);
        $this->storeBranding('app_favicon', $path);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Favicon berhasil diupload.']);

        return to_route('admin.pengaturan');
    }

    /**
     * Simpan branding di sekolah yang sedang aktif, bukan di tabel `settings`
     * global — kalau global, admin satu sekolah mengubah tampilan keenam tenant
     * sekaligus dan menghapus berkas milik sekolah lain.
     */
    private function storeBranding(string $key, string $path): void
    {
        $school = School::find(auth()->user()->school_id);

        if (! $school) {
            Setting::setValue($key, $path);

            return;
        }

        $old = $school->getSetting($key);

        // Hanya hapus berkas milik sekolah ini sendiri; nilai global lama
        // dibiarkan karena masih dipakai sekolah lain sebagai fallback.
        if ($old && $old !== Setting::getValue($key) && Storage::disk('public')->exists($old)) {
            Storage::disk('public')->delete($old);
        }

        $school->setSetting($key, $path);
    }
}
