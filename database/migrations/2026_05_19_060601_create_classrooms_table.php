<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('grade_level');
            $table->foreignUlid('homeroom_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('capacity')->default(36);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classrooms');
    }
};
