<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\GoogleDriveService;
use App\Services\ImageConverter;
use App\Services\Notification\DefaultWhatsAppGateway;
use App\Services\Penyelenggara\KartuDrive;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengaturan workspace Absensi General. Semua tersimpan di `settings` global
 * berprefiks `general_` — tidak pernah di `schools.settings`, jadi pengaturan
 * sekolah tidak ikut berubah (spec 009).
 */
class SettingsController extends Controller
{
    public const NAMA = 'general_nama';

    public const LOGO = 'general_logo';

    public const KUOTA_PESERTA = 'general_kuota_peserta_bawaan';

    public const KUOTA_WA = 'general_kuota_wa_bawaan';

    public const WA_GATEWAY = 'general_wa_gateway';

    public const WA_TOKEN = 'general_wa_fonnte_token';

    public function index(): Response
    {
        $logo = Setting::getValue(self::LOGO);

        return Inertia::render('general/pengaturan', [
            'umum' => [
                'nama' => Setting::getValue(self::NAMA, 'Absensi General'),
                'logo' => $logo ? Storage::disk('public')->url($logo) : null,
                'kuota_peserta_bawaan' => Setting::getValue(self::KUOTA_PESERTA),
                'kuota_wa_bawaan' => Setting::getValue(self::KUOTA_WA, 50),
            ],
            'drive' => [
                'folder' => app(KartuDrive::class)->rootFolderId(),
                'kredensial' => GoogleDriveService::hasGlobalCredentials(),
            ],
            'whatsapp' => [
                'gateway' => Setting::getValue(self::WA_GATEWAY, 'ozolab'),
                'token_terisi' => filled(Setting::getValue(self::WA_TOKEN)),
            ],
        ]);
    }

    public function umum(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'kuota_peserta_bawaan' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'kuota_wa_bawaan' => ['required', 'integer', 'min:0', 'max:100000'],
        ], [
            'nama.required' => 'Nama workspace wajib diisi.',
            'kuota_peserta_bawaan.min' => 'Kuota peserta minimal 1, atau kosongkan untuk tanpa batas.',
        ]);

        Setting::setValue(self::NAMA, $validated['nama']);
        Setting::setValue(self::KUOTA_PESERTA, $validated['kuota_peserta_bawaan'] ?? null);
        Setting::setValue(self::KUOTA_WA, $validated['kuota_wa_bawaan']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengaturan umum disimpan.']);

        return to_route('general.pengaturan');
    }

    public function logo(Request $request, ImageConverter $converter): RedirectResponse
    {
        $request->validate(
            ['logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']],
            ['logo.max' => 'Ukuran logo maksimal 2 MB.', 'logo.image' => 'Berkas logo harus berupa gambar.'],
        );

        $lama = Setting::getValue(self::LOGO);
        Setting::setValue(self::LOGO, $converter->storeAsWebp($request->file('logo'), 'images/branding', 'public', 85, 512));
        if ($lama) {
            Storage::disk('public')->delete($lama);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Logo workspace disimpan.']);

        return to_route('general.pengaturan');
    }

    /**
     * Folder root Drive kartu semua penyelenggara ("Tyas General Apps").
     * Menerima tautan folder Drive utuh atau ID-nya saja.
     */
    public function drive(Request $request): RedirectResponse
    {
        $validated = $request->validate(['folder' => ['nullable', 'string', 'max:300']]);

        $nilai = trim($validated['folder'] ?? '');
        if (preg_match('~/folders/([A-Za-z0-9_-]{10,})~', $nilai, $m)) {
            $nilai = $m[1];
        }

        if ($nilai !== '' && ! preg_match('/^[A-Za-z0-9_-]{10,}$/', $nilai)) {
            return back()->withErrors(['folder' => 'Bukan tautan atau ID folder Google Drive yang valid. Salin tautan dari address bar saat folder dibuka.']);
        }

        Setting::setValue(KartuDrive::SETTING_ROOT, $nilai !== '' ? $nilai : null);

        Inertia::flash('toast', ['type' => 'success', 'message' => $nilai !== '' ? 'Folder Drive disimpan.' : 'Folder Drive dikosongkan.']);

        return to_route('general.pengaturan');
    }

    /**
     * Gateway WhatsApp khusus general. Token Fonnte disimpan terenkripsi;
     * kosongkan isian token untuk mempertahankan token lama.
     */
    public function whatsapp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'gateway' => ['required', Rule::in(['ozolab', 'fonnte'])],
            'fonnte_token' => ['nullable', 'string', 'max:200'],
        ]);

        if ($validated['gateway'] === 'fonnte' && blank($validated['fonnte_token'] ?? null) && blank(Setting::getValue(self::WA_TOKEN))) {
            return back()->withErrors(['fonnte_token' => 'Isi token Fonnte untuk memakai gateway Fonnte.']);
        }

        Setting::setValue(self::WA_GATEWAY, $validated['gateway']);
        if (filled($validated['fonnte_token'] ?? null)) {
            Setting::setValue(self::WA_TOKEN, Crypt::encryptString($validated['fonnte_token']));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengaturan WhatsApp disimpan.']);

        return to_route('general.pengaturan');
    }

    public function ujiWhatsapp(Request $request, DefaultWhatsAppGateway $wa): JsonResponse
    {
        $validated = $request->validate(['nomor' => ['required', 'string', 'regex:/^\+?[0-9]{9,15}$/']], [
            'nomor.regex' => 'Nomor WhatsApp hanya angka, 9–15 digit (mis. 081234567890).',
        ]);

        $pesan = 'Tes WhatsApp dari '.Setting::getValue(self::NAMA, 'Absensi General').'. Jika pesan ini sampai, pengaturan berhasil.';
        $token = Setting::getValue(self::WA_TOKEN);

        $berhasil = Setting::getValue(self::WA_GATEWAY, 'ozolab') === 'fonnte' && $token
            ? $wa->sendTextViaFonnte($validated['nomor'], $pesan, Crypt::decryptString($token))
            : $wa->sendText($validated['nomor'], $pesan);

        return response()->json([
            'success' => $berhasil,
            'message' => $berhasil ? 'Pesan tes terkirim.' : 'Gagal mengirim. Periksa token dan nomor tujuan.',
        ]);
    }
}
