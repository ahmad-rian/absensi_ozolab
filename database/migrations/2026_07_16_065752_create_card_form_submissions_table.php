<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_form_submissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('card_form_id')->constrained()->cascadeOnDelete();
            $table->json('data');                    // { fieldKey: value }
            $table->string('photo_path')->nullable(); // cropped photo (if form has a photo field)
            $table->string('file_path')->nullable();  // local fallback when Drive off
            $table->string('drive_file_id')->nullable();
            $table->string('drive_url')->nullable();
            $table->string('status')->default('completed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_form_submissions');
    }
};
