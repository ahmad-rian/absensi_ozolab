<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Setting;
use App\Services\ImageConverter;
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
            'default_check_in_time',
            'late_threshold_time',
            'default_check_out_time',
            'timezone',
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
        }

        $logoPath = $school?->logo_path ?? ($settings['school_logo'] ?? '');
        $faviconPath = $school?->favicon_path ?? ($settings['school_favicon'] ?? '');

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
            'default_check_in_time' => ['nullable', 'string', 'max:10'],
            'late_threshold_time' => ['nullable', 'string', 'max:10'],
            'default_check_out_time' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'string', 'in:Asia/Jakarta,Asia/Makassar,Asia/Jayapura'],
            'whatsapp_enabled' => ['nullable'],
            'notify_on_check_in' => ['nullable'],
            'notify_on_check_out' => ['nullable'],
            'whatsapp_template_attendance' => ['nullable', 'string', 'max:2000'],
        ]);

        $school = School::find(auth()->user()->school_id);

        if ($school) {
            foreach ($validated as $key => $value) {
                $school->setSetting($key, $value);
            }

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
        $request->validate(['logo' => ['required', 'image', 'max:2048']]);

        $school = School::find(auth()->user()->school_id);
        $oldPath = $school?->logo_path ?? Setting::getValue('school_logo', '');

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $path = $converter->storeAsWebp($request->file('logo'), 'images/branding', 'public', 85, 512);

        if ($school) {
            $school->update(['logo_path' => $path]);
            $school->setSetting('school_logo', $path);
            $school->save();
        } else {
            Setting::setValue('school_logo', $path);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Logo berhasil diupload.']);

        return to_route('admin.pengaturan');
    }

    public function uploadFavicon(Request $request, ImageConverter $converter): RedirectResponse
    {
        $request->validate(['favicon' => ['required', 'image', 'max:2048']]);

        $school = School::find(auth()->user()->school_id);
        $oldPath = $school?->favicon_path ?? Setting::getValue('school_favicon', '');

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $path = $converter->storeAsWebp($request->file('favicon'), 'images/branding', 'public', 85, 128);

        if ($school) {
            $school->update(['favicon_path' => $path]);
            $school->setSetting('school_favicon', $path);
            $school->save();
        } else {
            Setting::setValue('school_favicon', $path);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Favicon berhasil diupload.']);

        return to_route('admin.pengaturan');
    }
}
