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
        // Kolom nullable saja: baris Kartu Bebas/haji lama tetap NULL (Konstitusi III).
        Schema::table('card_datasets', function (Blueprint $table) {
            $table->ulid('penyelenggara_id')->nullable()->index();
            $table->ulid('kategori_id')->nullable()->index();
        });

        Schema::table('card_forms', function (Blueprint $table) {
            $table->ulid('penyelenggara_id')->nullable()->index();
            $table->ulid('program_id')->nullable()->index();
        });

        Schema::table('card_form_submissions', function (Blueprint $table) {
            $table->ulid('penyelenggara_id')->nullable()->index();
            $table->ulid('program_id')->nullable()->index();
            $table->timestamp('archived_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('card_form_submissions', function (Blueprint $table) {
            $table->dropIndex(['penyelenggara_id']);
            $table->dropIndex(['program_id']);
            $table->dropColumn(['penyelenggara_id', 'program_id', 'archived_at']);
        });

        Schema::table('card_forms', function (Blueprint $table) {
            $table->dropIndex(['penyelenggara_id']);
            $table->dropIndex(['program_id']);
            $table->dropColumn(['penyelenggara_id', 'program_id']);
        });

        Schema::table('card_datasets', function (Blueprint $table) {
            $table->dropIndex(['penyelenggara_id']);
            $table->dropIndex(['kategori_id']);
            $table->dropColumn(['penyelenggara_id', 'kategori_id']);
        });
    }
};
