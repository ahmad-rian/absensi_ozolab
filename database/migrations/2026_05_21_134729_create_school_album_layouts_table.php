<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_album_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('paper_size')->default('A4');
            $table->string('orientation')->default('portrait');
            $table->unsignedTinyInteger('columns')->default(3);
            $table->unsignedTinyInteger('rows')->default(4);
            $table->json('layout_config');
            $table->string('thumbnail_path')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_album_layouts');
    }
};
