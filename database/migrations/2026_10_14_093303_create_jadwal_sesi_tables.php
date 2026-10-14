<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sesi_polas', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('penyelenggara_id')->constrained('penyelenggaras')->cascadeOnDelete();
            $table->foreignUlid('program_id')->constrained('programs')->cascadeOnDelete();
            $table->foreignUlid('grup_id')->nullable()->constrained('grups')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('pengulangan', 12);
            $table->json('hari')->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->date('tanggal')->nullable();
            $table->time('datang_mulai');
            $table->time('datang_selesai');
            $table->time('batas_terlambat')->nullable();
            $table->time('pulang_mulai')->nullable();
            $table->time('pulang_selesai')->nullable();
            $table->timestamps();
        });

        Schema::create('sesis', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('penyelenggara_id')->constrained('penyelenggaras')->cascadeOnDelete();
            $table->foreignUlid('program_id')->constrained('programs')->cascadeOnDelete();
            $table->foreignUlid('sesi_pola_id')->nullable()->constrained('sesi_polas')->nullOnDelete();
            $table->foreignUlid('grup_id')->nullable()->constrained('grups')->cascadeOnDelete();
            $table->string('name', 80);
            $table->date('tanggal');
            $table->time('datang_mulai');
            $table->time('datang_selesai');
            $table->time('batas_terlambat')->nullable();
            $table->time('pulang_mulai')->nullable();
            $table->time('pulang_selesai')->nullable();
            $table->timestamps();

            $table->unique(['sesi_pola_id', 'tanggal']);
            $table->index(['program_id', 'tanggal']);
        });

        Schema::create('liburs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('penyelenggara_id')->constrained('penyelenggaras')->cascadeOnDelete();
            $table->foreignUlid('program_id')->constrained('programs')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('keterangan', 120)->nullable();
            $table->timestamps();

            $table->unique(['program_id', 'tanggal']);
        });

        Schema::create('kehadirans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('penyelenggara_id')->constrained('penyelenggaras')->cascadeOnDelete();
            $table->foreignUlid('program_id')->constrained('programs')->cascadeOnDelete();
            $table->foreignUlid('sesi_id')->constrained('sesis')->cascadeOnDelete();
            $table->foreignUlid('card_form_submission_id')->constrained('card_form_submissions')->cascadeOnDelete();
            $table->string('status', 16);
            $table->dateTime('datang_at')->nullable();
            $table->dateTime('pulang_at')->nullable();
            $table->string('sumber', 16);
            $table->string('catatan', 500)->nullable();
            $table->foreignUlid('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['sesi_id', 'card_form_submission_id']);
            $table->index(['program_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kehadirans');
        Schema::dropIfExists('liburs');
        Schema::dropIfExists('sesis');
        Schema::dropIfExists('sesi_polas');
    }
};
