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
        Schema::table('card_form_submissions', function (Blueprint $table) {
            $table->string('original_photo_path')->nullable();
            $table->json('manual_crop')->nullable();
            $table->uuid('generation_token')->nullable();
            $table->text('generation_error')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('card_form_submissions', function (Blueprint $table) {
            $table->dropColumn(['original_photo_path', 'manual_crop', 'generation_token', 'generation_error']);
        });
    }
};
