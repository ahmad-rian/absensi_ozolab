<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak setiap scan kartu Haji (haji lama maupun program penyelenggara).
 *
 * Tabel baru, aditif: `card_attendances` dan `kehadirans` tetap satu baris per
 * hari / sesi (Masuk = scan pertama, Pulang = scan terakhir); tabel ini yang
 * menyimpan semua scan di antaranya untuk riwayat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_scans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('card_form_submission_id')->constrained('card_form_submissions')->cascadeOnDelete();
            // Haji lama: layout kartu. Program: program + sesi. Salah satu terisi.
            $table->foreignUlid('card_form_id')->nullable()->constrained('card_forms')->cascadeOnDelete();
            $table->foreignUlid('program_id')->nullable()->constrained('programs')->cascadeOnDelete();
            $table->foreignUlid('sesi_id')->nullable()->constrained('sesis')->cascadeOnDelete();
            $table->string('jenis', 16);
            // Jam dinding (Asia/Jakarta untuk haji lama, zona program untuk program),
            // sama seperti check_in / datang_at.
            $table->dateTime('scanned_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['card_form_submission_id', 'scanned_at'], 'card_scans_peserta_waktu_idx');
            $table->index(['card_form_id', 'scanned_at'], 'card_scans_form_waktu_idx');
            $table->index(['sesi_id', 'scanned_at'], 'card_scans_sesi_waktu_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_scans');
    }
};
