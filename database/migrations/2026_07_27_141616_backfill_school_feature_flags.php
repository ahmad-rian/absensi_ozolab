<?php

use App\Models\School;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Snapshot literal, sengaja tidak memanggil App\Enums\SchoolFeature.
     * Migrasi adalah sejarah: kalau suatu hari sebuah case di-rename atau
     * dihapus, `migrate:fresh` tidak boleh ikut pecah.
     *
     * Fitur yang memetakan ke key lama (`prayer_enabled`, `prayer_dhuha_enabled`,
     * `whatsapp_enabled`) sengaja TIDAK ada di sini — key-nya sudah jadi sumber
     * kebenaran yang sama, jadi tidak ada yang perlu di-backfill.
     *
     * @var array<string, bool>
     */
    private const FEATURES = [
        'feature_master_siswa' => true,
        'feature_absensi_sekolah' => true,
        'feature_laporan' => true,
        'feature_notif_alpa_sholat' => false,
        'feature_inbox_notifikasi' => true,
        'feature_kartu_album' => true,
        'feature_pendaftaran_publik' => true,
        'feature_pendaftaran_telegram' => true,
        'feature_manajemen_pengguna' => true,
        'feature_integrasi_drive' => true,
        'feature_integrasi_whatsapp' => true,
    ];

    public function up(): void
    {
        // withTrashed(): School memakai SoftDeletes, dan sekolah yang di-restore
        // nanti harus punya peta fitur yang sama lengkapnya.
        School::withTrashed()->cursor()->each(function (School $school): void {
            $settings = $school->settings ?? [];
            $changed = false;

            foreach (self::FEATURES as $key => $default) {
                // array_key_exists, bukan `??`: nilai `false` yang sudah pernah
                // disimpan admin tidak boleh ikut ditimpa jadi `true`.
                if (array_key_exists($key, $settings) && $settings[$key] !== null) {
                    continue;
                }

                $settings[$key] = $default;
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
        School::withTrashed()->cursor()->each(function (School $school): void {
            $settings = $school->settings ?? [];

            foreach (array_keys(self::FEATURES) as $key) {
                unset($settings[$key]);
            }

            $school->settings = $settings;
            $school->saveQuietly();
        });
    }
};
