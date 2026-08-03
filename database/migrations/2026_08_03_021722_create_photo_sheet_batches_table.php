<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_sheet_batches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->constrained()->cascadeOnDelete();
            $table->string('template');
            $table->string('status')->default('processing');
            // Komposisi cetak: [{student_id, name, quantity}]. Sengaja JSON —
            // ini jejak cetak sekali pakai, tidak pernah di-query per siswa.
            $table->json('items');
            $table->unsignedInteger('total_slots')->default(0);
            $table->unsignedInteger('pages')->default(0);
            $table->string('file_path')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_sheet_batches');
    }
};
