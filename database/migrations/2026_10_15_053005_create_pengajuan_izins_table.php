<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengajuan izin/sakit siswa dari orang tua (portal atau form publik /izin).
 * Tabel baru — tidak mengubah tabel absensi yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_izins', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUlid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUlid('parent_profile_id')->nullable()->constrained('parent_profiles')->nullOnDelete();
            $table->string('sumber', 10); // portal | publik
            $table->string('jenis', 10); // IZIN | SAKIT
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->text('alasan');
            $table->string('bukti_path')->nullable();
            $table->string('pengirim_nama', 100)->nullable();
            $table->string('pengirim_telepon', 20)->nullable();
            $table->string('status', 12)->default('menunggu'); // menunggu | disetujui | ditolak
            $table->text('catatan_review')->nullable();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedSmallInteger('hari_dicatat')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status', 'created_at']);
            $table->index(['student_id', 'tanggal_mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_izins');
    }
};
