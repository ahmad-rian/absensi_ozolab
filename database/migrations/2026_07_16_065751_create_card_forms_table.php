<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_forms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('created_by')->nullable();
            $table->string('name');
            $table->string('token', 64)->unique();           // unguessable public link
            $table->json('fields');                           // [{key,label,type,required,options}]
            $table->string('orientation')->default('landscape');
            $table->ulid('frame_id')->nullable();
            $table->json('layout_config');                    // elements schema (source = field key)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_forms');
    }
};
