<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_drive_configs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->constrained()->cascadeOnDelete();
            $table->text('service_account_json')->nullable();
            $table->string('root_folder_id')->nullable();
            $table->string('cards_folder_id')->nullable();
            $table->string('albums_folder_id')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();

            $table->unique('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_drive_configs');
    }
};
