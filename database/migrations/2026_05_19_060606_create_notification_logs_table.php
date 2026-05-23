<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('student_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('attendance_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('parent_profile_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('whatsapp_number');
            $table->string('template_key')->nullable();
            $table->json('payload')->nullable();
            $table->json('response_body')->nullable();
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('attempt_count')->default(1);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
