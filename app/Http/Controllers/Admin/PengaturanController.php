<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrayerType;
use App\Enums\SchoolFeature;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Setting;
use App\Services\ImageConverter;
use App\Support\PrayerSchedule;
use App\Support\PrayerSettings;
use App\Support\SchoolFeatures;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Validator;
use Inertia\Inertia;
use Inertia\Response;

class PengaturanController extends Controller
{
    /**
     * Aturan dipisah per tab karena validasi lintas-field (jam selesai harus
     * setelah jam mulai, jendela Dhuha tidak boleh beririsan dengan Dzuhur)
     * hanya masuk akal di dalam satu kelompok. Tanpa pemisahan ini,
     * `after:prayer_start` lolos diam-diam ketika `prayer_start` tidak ikut
     * dikirim.
     *
     * @var array<string, array<string, array<int, mixed>>>
     */
    private const SECTION_RULES = [
        'umum' => [
            'school_name' => ['nullable', 'string', 'max:255'],
            // Jam masuk/pulang tidak diatur di sini — sumber kebenarannya
            // adalah menu Jadwal Absensi (tabel attendance_schedules).
            'timezone' => ['nullable', 'string', 'in:Asia/Jakarta,Asia/Makassar,Asia/Jayapura'],
        ],
        'sholat' => [
            'prayer_dhuha_enabled' => ['required', 'boolean'],
            'prayer_dhuha_start' => ['required', 'date_format:H:i'],
            'prayer_dhuha_end' => ['required', 'date_format:H:i', 'after:prayer_dhuha_start'],
            'prayer_enabled' => ['required', 'boolean'],
            'prayer_start' => ['required', 'date_format:H:i'],
            'prayer_end' => ['required', 'date_format:H:i', 'after:prayer_start'],
            'prayer_all_religions' => ['required', 'boolean'],
        ],
        'notifikasi' => [
            'whatsapp_enabled' => ['required', 'boolean'],
            'notify_on_check_in' => ['required', 'boolean'],
            'notify_on_check_out' => ['required', 'boolean'],
            'prayer_absence_threshold' => ['required', 'integer', 'between:2,10'],
            'prayer_absence_require_present' => ['required', 'boolean'],
            'whatsapp_template_attendance' => ['nullable', 'string', 'max:2000'],
            'whatsapp_template_prayer_absence' => ['nullable', 'string', 'max:2000'],
        ],
    ];

    /**
     * Ruleset lama, dipakai kalau payload tidak membawa `section`.
     *
     * Ini katup pengamannya: bundle JS lama yang masih di cache browser saat
     * deploy tetap bisa menyimpan tanpa 422.
     *
     * @var array<string, array<int, mixed>>
     */
    private const LEGACY_RULES = [
        'school_name' => ['nullable', 'string', 'max:255'],
        'timezone' => ['nullable', 'string', 'in:Asia/Jakarta,Asia/Makassar,Asia/Jayapura'],
        'prayer_enabled' => ['nullable', 'boolean'],
        'prayer_start' => ['nullable', 'date_format:H:i'],
        'prayer_end' => ['nullable', 'date_format:H:i', 'after:prayer_start'],
        'prayer_all_religions' => ['nullable', 'boolean'],
        'whatsapp_enabled' => ['nullable'],
        'notify_on_check_in' => ['nullable'],
        'notify_on_check_out' => ['nullable'],
        'whatsapp_template_attendance' => ['nullable', 'string', 'max:2000'],
    ];

    /**
     * Key yang harus disimpan sebagai boolean asli.
     *
     * `School::getSetting()` memakai `??`, sehingga `null` tersimpan tidak
     * terbedakan dari "belum pernah diatur" dan diam-diam kembali ke default
     * `true` di DispatchAttendanceNotifications — notifikasi menyala sendiri.
     *
     * @var array<int, string>
     */
    private const BOOLEAN_KEYS = [
        'prayer_enabled',
        'prayer_dhuha_enabled',
        'prayer_all_religions',
        'whatsapp_enabled',
        'notify_on_check_in',
        'notify_on_check_out',
        'prayer_absence_require_present',
    ];

    public function index(): Response
    {
        $school = School::find(auth()->user()->school_id);

        $keys = [
            'school_name',
            'timezone',
            'whatsapp_enabled',
            'notify_on_check_in',
            'notify_on_check_out',
            'whatsapp_template_attendance',
            'whatsapp_template_prayer_absence',
        ];

        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = $school?->getSetting($key) ?? Setting::getValue($key, '');
        }

        $settings['prayer_absence_threshold'] = (int) ($school?->getSetting('prayer_absence_threshold') ?? 3);
        $settings['prayer_absence_require_present'] = (bool) ($school?->getSetting('prayer_absence_require_present') ?? true);

        if ($school) {
            $settings['school_name'] = $school->getSetting('school_name') ?? $school->name;

            // Jam sholat jatuh ke default config kalau sekolah belum pernah
            // menyimpannya, supaya form tidak tampil kosong.
            foreach (PrayerType::chronological() as $type) {
                $prayer = PrayerSettings::for($school, $type);
                $settings[$type->settingKey('enabled')] = $prayer->enabled;
                $settings[$type->settingKey('start')] = $prayer->displayStart();
                $settings[$type->settingKey('end')] = $prayer->displayEnd();
            }

            $settings['prayer_all_religions'] = PrayerSettings::for($school)->allReligions;
        }

        // Branding per sekolah, dengan fallback ke nilai global lama supaya
        // sekolah yang belum pernah mengunggah tidak kehilangan logonya.
        $logoPath = $school?->getSetting('app_logo') ?: Setting::getValue('app_logo', '');
        $faviconPath = $school?->getSetting('app_favicon') ?: Setting::getValue('app_favicon', '');

        return Inertia::render('admin/pengaturan/index', [
            'settings' => $settings,
            'features' => SchoolFeatures::for($school)->toArray(),
            'featureCatalog' => $this->featureCatalog(),
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
        $section = (string) $request->input('section', '');

        if ($section === 'fitur') {
            return $this->updateFeatures($request);
        }

        $rules = self::SECTION_RULES[$section] ?? self::LEGACY_RULES;

        $validator = validator($request->all(), $rules);

        if ($section === 'sholat') {
            $this->assertPrayerWindowsDoNotOverlap($validator);
        }

        $validated = $validator->validate();

        // `section` bukan pengaturan; kalau ikut ter-merge ia mengotori
        // schools.settings dengan key sampah yang tidak pernah dibaca siapa pun.
        unset($validated['section']);

        foreach (self::BOOLEAN_KEYS as $key) {
            if (array_key_exists($key, $validated)) {
                $validated[$key] = (bool) $validated[$key];
            }
        }

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

        return $this->backToTab($section);
    }

    /**
     * Tab Fitur punya bentuk payload sendiri (objek bersarang), supaya `false`
     * selalu ikut terkirim dan key asing ditolak alih-alih menumpuk di
     * schools.settings.
     */
    private function updateFeatures(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'features' => ['required', 'array'],
            'features.*' => ['boolean'],
        ]);

        $school = School::find(auth()->user()->school_id);

        if (! $school) {
            return $this->backToTab('fitur');
        }

        $settings = $school->settings ?? [];

        foreach (SchoolFeature::cases() as $feature) {
            if (! array_key_exists($feature->value, $validated['features'])) {
                continue;
            }

            $settings[$feature->settingKey()] = (bool) $validated['features'][$feature->value];
        }

        $school->settings = $settings;
        $school->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fitur berhasil diperbarui.']);

        return $this->backToTab('fitur');
    }

    /**
     * Deteksi-jam di scanner hanya bisa bekerja kalau jendela Dhuha dan Dzuhur
     * tidak beririsan. Batas jendela inklusif di kedua ujung, jadi "selesai
     * 09:00" + "mulai 09:00" pun sudah ambigu.
     */
    private function assertPrayerWindowsDoNotOverlap(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();

            if (! ($data['prayer_dhuha_enabled'] ?? false) || ! ($data['prayer_enabled'] ?? false)) {
                return;
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $school = new School(['settings' => [
                'prayer_dhuha_enabled' => true,
                'prayer_dhuha_start' => $data['prayer_dhuha_start'],
                'prayer_dhuha_end' => $data['prayer_dhuha_end'],
                'prayer_enabled' => true,
                'prayer_start' => $data['prayer_start'],
                'prayer_end' => $data['prayer_end'],
            ]]);

            if (PrayerSchedule::for($school)->overlapping() !== []) {
                $validator->errors()->add(
                    'prayer_dhuha_end',
                    'Jendela Dhuha tidak boleh beririsan dengan jendela Dzuhur.'
                );
            }
        });
    }

    /**
     * Kembali ke tab yang barusan disimpan; tanpa parameter ini redirect
     * membuang query string dan admin terlempar ke tab pertama.
     */
    private function backToTab(string $section): RedirectResponse
    {
        return to_route('admin.pengaturan', $section !== '' ? ['tab' => $section] : []);
    }

    /**
     * Daftar fitur untuk tab Fitur, plus modul yang tidak pernah bisa
     * dimatikan supaya admin melihat gambaran utuh.
     *
     * @return array{features: array<int, array<string, mixed>>, always_on: array<int, string>}
     */
    private function featureCatalog(): array
    {
        return [
            'features' => array_map(fn (SchoolFeature $feature) => [
                'key' => $feature->value,
                'label' => $feature->label(),
                'description' => $feature->description(),
                'group' => $feature->group(),
            ], SchoolFeature::cases()),
            'always_on' => array_map(
                fn ($module) => $module->label(),
                SchoolFeature::alwaysOnModules(),
            ),
        ];
    }

    public function uploadLogo(Request $request, ImageConverter $converter): RedirectResponse
    {
        $request->validate(['logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);

        $path = $converter->storeAsWebp($request->file('logo'), 'images/branding', 'public', 85, 512);
        $this->storeBranding('app_logo', $path);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Logo berhasil diupload.']);

        return $this->backToTab('tampilan');
    }

    public function uploadFavicon(Request $request, ImageConverter $converter): RedirectResponse
    {
        $request->validate(['favicon' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,ico', 'max:2048']]);

        $path = $converter->storeAsWebp($request->file('favicon'), 'images/branding', 'public', 85, 128);
        $this->storeBranding('app_favicon', $path);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Favicon berhasil diupload.']);

        return $this->backToTab('tampilan');
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
