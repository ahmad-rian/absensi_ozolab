<?php

use App\Models\School;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Balik kebijakan WhatsApp untuk seluruh sekolah yang sudah ada.
     *
     * Kuncinya ditulis EKSPLISIT, bukan disandarkan pada nilai default di kode.
     * `School::getSetting()` memakai `??`, jadi kunci yang tidak pernah ditulis
     * tidak terbedakan dari `null` tersimpan, dan cabang default-nya tersebar di
     * beberapa berkas yang gampang bergeser sendiri-sendiri.
     *
     * Setelah migrasi ini, WA "anak Anda hadir" berhenti untuk semua sekolah dan
     * digantikan sapuan `attendance:notify-absence`. Email dan Telegram tidak
     * disentuh sama sekali — keduanya tetap tunduk pada `notify_on_check_in` dan
     * `notify_on_check_out` seperti sebelumnya.
     */
    private const DEFAULTS = [
        'wa_alert_only' => true,
        'wa_alert_terlambat' => true,
        'wa_alert_alpa' => true,
        'wa_verified' => false,
        'wa_daily_limit' => 50,
    ];

    public function up(): void
    {
        School::withTrashed()->get()->each(function (School $school) {
            $settings = $school->settings ?? [];

            foreach (self::DEFAULTS as $key => $value) {
                $settings[$key] = $value;
            }

            $school->settings = $settings;
            $school->saveQuietly();
        });
    }

    public function down(): void
    {
        School::withTrashed()->get()->each(function (School $school) {
            $settings = $school->settings ?? [];

            foreach (array_keys(self::DEFAULTS) as $key) {
                unset($settings[$key]);
            }

            $school->settings = $settings;
            $school->saveQuietly();
        });
    }
};
