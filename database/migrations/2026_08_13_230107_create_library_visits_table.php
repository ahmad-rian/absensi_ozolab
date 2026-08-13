<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kunjungan perpustakaan.
 *
 * Tabel sendiri, bukan baris baru di `attendances`, dengan alasan yang sama
 * seperti `prayer_attendances`: belasan query dashboard dan laporan tidak
 * memfilter `type`, jadi baris kunjungan akan menggelembungkan statistik
 * kehadiran sekolah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_visits', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('student_id')->constrained()->cascadeOnDelete();
            $table->date('visit_date');
            $table->timestamp('entered_at');
            // Null berarti siswa masih di dalam, atau lupa scan saat keluar.
            $table->timestamp('exited_at')->nullable();
            $table->string('device_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Sengaja TANPA unique: satu siswa boleh keluar-masuk berkali-kali
            // dalam sehari, dan setiap kunjungan adalah barisnya sendiri.
            // Penjaga tap ganda ada di LibraryVisitRecorder.
            $table->index(['school_id', 'visit_date']);
            $table->index(['student_id', 'visit_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_visits');
    }
};
