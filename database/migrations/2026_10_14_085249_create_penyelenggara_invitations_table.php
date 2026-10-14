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
        Schema::create('penyelenggara_invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('penyelenggara_id')->constrained('penyelenggaras')->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 32);
            $table->string('token_hash', 64)->unique();
            $table->foreignUlid('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['penyelenggara_id', 'email'], 'peny_invites_penyelenggara_email_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penyelenggara_invitations');
    }
};
