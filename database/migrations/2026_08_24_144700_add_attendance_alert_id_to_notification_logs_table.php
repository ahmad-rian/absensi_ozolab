<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kunci korelasi ketiga, alasannya persis sama dengan
     * `prayer_absence_alert_id`: tanpa kolom sendiri, `deliver()` mencari log
     * lama lewat `WHERE attendance_id = NULL` yang tidak pernah cocok, dan tiap
     * retry mengirim ulang pesan ke HP orang tua.
     *
     * Indeks `(school_id, channel, sent_at)` melayani penghitung kuota harian
     * WhatsApp. Ia dijalankan sebelum SETIAP pesan WA, jadi tanpa indeks ia
     * memindai seluruh tabel log berkali-kali per menit saat sapuan berjalan.
     */
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->foreignUlid('attendance_alert_id')
                ->nullable()
                ->after('prayer_absence_alert_id')
                ->constrained('attendance_alerts')
                ->cascadeOnDelete();

            $table->index(['school_id', 'channel', 'sent_at'], 'notification_logs_kuota_index');
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropIndex('notification_logs_kuota_index');
            $table->dropConstrainedForeignId('attendance_alert_id');
        });
    }
};
