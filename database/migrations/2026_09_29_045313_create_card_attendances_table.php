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
        Schema::create('card_attendances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('card_form_submission_id')->constrained('card_form_submissions')->cascadeOnDelete();
            $table->date('attendance_date')->index();
            $table->string('status', 16)->default('hadir');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->string('note', 1000)->nullable();
            $table->string('recorded_by')->nullable();
            $table->unique(['card_form_submission_id', 'attendance_date'], 'card_attendance_participant_date_unique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_attendances');
    }
};
