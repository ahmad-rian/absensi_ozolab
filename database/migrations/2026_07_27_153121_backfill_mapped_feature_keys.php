<?php

use App\Models\School;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Tuliskan ketiga saklar ber-key-lama secara EKSPLISIT ke setiap sekolah.
     *
     * Ketiganya dibaca lewat dua jalur — `SchoolFeatures` (tab Fitur, sidebar,
     * middleware) dan `PrayerSettings`/`DispatchAttendanceNotifications`
     * (perilaku sebenarnya). Selama key-nya belum pernah ditulis, setiap jalur
     * memakai default-nya masing-masing, dan perbedaan sekecil apa pun di
     * antara keduanya langsung jadi bug diam-diam.
     *
     * Nilai yang ditulis adalah nilai yang SUDAH berlaku hari ini, jadi tidak
     * ada satu pun sekolah yang berubah perilakunya. Setelah migrasi ini
     * keadaan "belum pernah diatur" tidak ada lagi untuk ketiganya.
     *
     * Key literal, tidak memanggil enum: migrasi adalah sejarah, dan rename
     * case suatu hari tidak boleh memecah `migrate:fresh`.
     *
     * @var array<string, array{0: string, 1: bool}> key => [config, default pabrikan]
     */
    private const KEYS = [
        'prayer_enabled' => ['attendance.prayer.enabled', false],
        'prayer_dhuha_enabled' => ['attendance.prayer_dhuha.enabled', false],
        // DispatchAttendanceNotifications memakai default true.
        'whatsapp_enabled' => [null, true],
    ];

    public function up(): void
    {
        School::withTrashed()->cursor()->each(function (School $school): void {
            $settings = $school->settings ?? [];
            $changed = false;

            foreach (self::KEYS as $key => [$configKey, $fallback]) {
                // array_key_exists, bukan `??`: `false` yang sudah pernah
                // disimpan admin tidak boleh ikut ditimpa.
                if (array_key_exists($key, $settings) && $settings[$key] !== null) {
                    // Sekalian normalkan tipenya — nilai lama bisa berupa
                    // string kosong hasil hidrasi form yang keliru.
                    $normalized = (bool) $settings[$key];

                    if ($settings[$key] !== $normalized) {
                        $settings[$key] = $normalized;
                        $changed = true;
                    }

                    continue;
                }

                $settings[$key] = $configKey !== null
                    ? (bool) config($configKey, $fallback)
                    : $fallback;
                $changed = true;
            }

            if ($changed) {
                $school->settings = $settings;
                $school->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        // Sengaja tidak menghapus key-nya: nilainya sama dengan yang berlaku
        // sebelum migrasi, jadi membiarkannya tidak mengubah apa pun,
        // sedangkan menghapusnya justru mengembalikan ambiguitas.
    }
};
