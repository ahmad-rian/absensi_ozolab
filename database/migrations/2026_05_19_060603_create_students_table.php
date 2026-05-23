<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('parent_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nis')->unique();
            $table->string('nisn')->unique()->nullable();
            $table->string('full_name');
            $table->string('gender');
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->text('address')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('qr_token', 64)->unique()->nullable();
            $table->timestamp('qr_issued_at')->nullable();
            $table->timestamp('qr_rotated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
