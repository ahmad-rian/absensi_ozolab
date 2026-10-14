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
        Schema::create('kategoris', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('penyelenggara_id')->constrained('penyelenggaras')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->string('template', 40)->nullable();
            $table->string('istilah_peserta', 40)->default('Peserta');
            $table->string('istilah_grup', 40)->default('Grup');
            $table->json('features')->nullable();
            $table->ulid('card_dataset_id')->nullable()->index();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['penyelenggara_id', 'slug']);
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('penyelenggara_id')->constrained('penyelenggaras')->cascadeOnDelete();
            $table->foreignUlid('kategori_id')->constrained('kategoris')->cascadeOnDelete();
            $table->string('name', 150);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('timezone', 40)->default('Asia/Jakarta');
            $table->ulid('card_form_id')->nullable()->index();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('grups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('penyelenggara_id')->constrained('penyelenggaras')->cascadeOnDelete();
            $table->foreignUlid('program_id')->constrained('programs')->cascadeOnDelete();
            $table->foreignUlid('parent_id')->nullable()->constrained('grups')->restrictOnDelete();
            $table->string('name', 120);
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('grup_peserta', function (Blueprint $table) {
            $table->foreignUlid('grup_id')->constrained('grups')->cascadeOnDelete();
            $table->foreignUlid('card_form_submission_id')->constrained('card_form_submissions')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['grup_id', 'card_form_submission_id']);
        });

        Schema::create('grup_peserta_riwayats', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('penyelenggara_id')->constrained('penyelenggaras')->cascadeOnDelete();
            $table->foreignUlid('card_form_submission_id')->constrained('card_form_submissions')->cascadeOnDelete();
            $table->ulid('dari_grup_id')->nullable();
            $table->ulid('ke_grup_id')->nullable();
            $table->string('aksi', 16);
            $table->foreignUlid('oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('grup_pendamping', function (Blueprint $table) {
            $table->foreignUlid('grup_id')->constrained('grups')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['grup_id', 'user_id']);
        });

        Schema::create('peserta_imports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('penyelenggara_id')->constrained('penyelenggaras')->cascadeOnDelete();
            $table->foreignUlid('program_id')->constrained('programs')->cascadeOnDelete();
            $table->string('filename');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('berhasil')->default(0);
            $table->unsignedInteger('gagal')->default(0);
            $table->json('errors')->nullable();
            $table->foreignUlid('oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('peserta_imports');
        Schema::dropIfExists('grup_pendamping');
        Schema::dropIfExists('grup_peserta_riwayats');
        Schema::dropIfExists('grup_peserta');
        Schema::dropIfExists('grups');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('kategoris');
    }
};
