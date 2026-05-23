<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_generation_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('school_card_layout_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('card');
            $table->string('status')->default('pending');
            $table->string('file_path')->nullable();
            $table->string('drive_file_id')->nullable();
            $table->string('drive_url')->nullable();
            $table->string('generated_by')->default('admin');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'student_id']);
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_generation_logs');
    }
};
