<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * State anti-kirim-ulang untuk peringatan terlambat / tidak hadir.
     *
     * Alasannya sama dengan `prayer_absence_alerts`: admin boleh menghapus baris
     * dari inbox notifikasi, jadi `notification_logs` tidak bisa jadi penjaga
     * anti-duplikat, dan cache hilang saat deploy dengan akibat yang sama.
     *
     * Ia juga menyediakan kolom korelasi untuk siswa yang TIDAK punya baris
     * absensi sama sekali — "tidak datang" tidak bisa ditunjuk lewat
     * `attendance_id`, dan `deliver()` mengunci idempotensi pada kolom korelasi.
     */
    public function up(): void
    {
        Schema::create('attendance_alerts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('student_id')->constrained()->cascadeOnDelete();
            // Terisi hanya untuk TERLAMBAT; yang tidak datang tidak punya baris.
            $table->foreignUlid('attendance_id')->nullable()->constrained()->nullOnDelete();
            $table->date('alert_date');
            $table->string('kind', 20);
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            // Penjaga utamanya. Sapuan berjalan tiap jam, jadi tanpa ini satu
            // siswa terlambat akan dikabarkan ulang setiap jam sampai tengah
            // malam. Nama indeks dipendekkan sendiri: nama bawaan Laravel
            // menembus batas 64 karakter MySQL.
            $table->unique(['student_id', 'alert_date', 'kind'], 'attendance_alerts_harian_unique');
            $table->index(['school_id', 'alert_date'], 'attendance_alerts_sekolah_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_alerts');
    }
};
