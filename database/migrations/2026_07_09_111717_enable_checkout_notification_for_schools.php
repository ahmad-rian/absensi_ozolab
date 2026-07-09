<?php

use App\Models\School;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Aktifkan notifikasi absen pulang (check-out) untuk semua sekolah existing.
     * Sebelumnya default-nya false, jadi notif pulang tidak pernah terkirim.
     */
    public function up(): void
    {
        School::withTrashed()->get()->each(function (School $school) {
            $settings = $school->settings ?? [];
            $settings['notify_on_check_out'] = true;
            $school->settings = $settings;
            $school->saveQuietly();
        });
    }

    public function down(): void
    {
        School::withTrashed()->get()->each(function (School $school) {
            $settings = $school->settings ?? [];
            $settings['notify_on_check_out'] = false;
            $school->settings = $settings;
            $school->saveQuietly();
        });
    }
};
