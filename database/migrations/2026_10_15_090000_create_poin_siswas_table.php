<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan poin pelanggaran siswa. Tabel baru — absensi tidak disentuh.
 *
 * `kunci_otomatis` berisi 'auto' untuk poin dari sapuan (TERLAMBAT/ALPA) dan
 * NULL untuk catatan guru BK. Unique key-nya mencegah sapuan mencatat dua kali,
 * sementara NULL tidak ikut unique sehingga catatan manual boleh banyak per hari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poin_siswas', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUlid('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('sumber', 10); // TERLAMBAT | ALPA | MANUAL
            $table->string('kunci_otomatis', 4)->nullable();
            $table->string('kategori', 100)->nullable();
            $table->unsignedSmallInteger('poin');
            $table->text('alasan')->nullable();
            $table->foreignUlid('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'tanggal', 'sumber', 'kunci_otomatis'], 'poin_siswas_otomatis_unique');
            $table->index(['school_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poin_siswas');
    }
};
