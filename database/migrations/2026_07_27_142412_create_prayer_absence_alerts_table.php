<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * State anti-kirim-ulang untuk peringatan "N hari berturut-turut tidak
     * sholat".
     *
     * Sengaja tabel sendiri, bukan menumpang `notification_logs`: admin bisa
     * menghapus baris log dari inbox notifikasi, dan itu akan memicu blast
     * ulang ke seluruh orang tua. Cache juga tidak dipakai karena hilang saat
     * `cache:clear` atau deploy, dengan akibat yang persis sama.
     */
    public function up(): void
    {
        Schema::create('prayer_absence_alerts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('student_id')->constrained()->cascadeOnDelete();
            // Kolomnya ada sejak awal supaya penambahan jenis sholat berikutnya
            // tidak butuh migrasi kedua.
            $table->string('prayer_type', 20);
            $table->date('streak_start_date');
            $table->date('streak_last_date');
            $table->unsignedTinyInteger('streak_length');
            // Label jenis sholat yang digabung ke dalam satu pesan. Dua pesan
            // nyaris identik dalam satu detik terbaca sebagai spam, jadi alert
            // dengan rentetan terpanjang jadi primary dan membawa label yang
            // lain di sini. Harus persisten: job-nya berjalan asinkron.
            $table->json('combined_types')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // Anti-kirim-ulang untuk satu rentetan yang sama. Ini BUKAN penjaga
            // utama: rentetan yang lebih panjang dari jendela pindai menggeser
            // streak_start_date tiap hari, jadi command juga memeriksa "ada
            // alert yang belum resolved dan sudah notified".
            $table->unique(['student_id', 'prayer_type', 'streak_start_date'], 'prayer_absence_alerts_streak_unique');
            $table->index(['school_id', 'prayer_type', 'resolved_at'], 'prayer_absence_alerts_open_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prayer_absence_alerts');
    }
};
