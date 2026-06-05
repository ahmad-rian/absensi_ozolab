<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_wa_configs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('fonnte_token');
            $table->string('display_phone')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_wa_configs');
    }
};
