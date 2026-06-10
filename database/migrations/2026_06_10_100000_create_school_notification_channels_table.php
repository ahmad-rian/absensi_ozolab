<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_notification_channels', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->boolean('is_active')->default(false);
            $table->text('settings')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_notification_channels');
    }
};
