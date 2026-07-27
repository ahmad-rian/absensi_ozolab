<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kunci korelasi kedua untuk `notification_logs`.
     *
     * `NotificationDispatcher::deliver()` mencari log yang sudah ada lewat
     * `where('attendance_id', ...)`. Untuk notifikasi non-absensi nilainya null,
     * dan `WHERE attendance_id = NULL` tidak pernah cocok dengan apa pun —
     * termasuk baris NULL — sehingga tiap retry akan mengirim ulang pesannya.
     * Menggantinya dengan `whereNull()` justru lebih parah: itu cocok dengan
     * SEMUA log alpa sholat, jadi satu baris terkirim mendiamkan seluruh
     * sekolah. Kolom ini yang membuat korelasinya kembali tepat satu baris.
     */
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->foreignUlid('prayer_absence_alert_id')
                ->nullable()
                ->after('attendance_id')
                ->constrained('prayer_absence_alerts')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prayer_absence_alert_id');
        });
    }
};
