<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_frames', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('image_path');
            $table->unsignedInteger('width')->default(0);
            $table->unsignedInteger('height')->default(0);
            $table->string('category')->default('card');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['school_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_frames');
    }
};
