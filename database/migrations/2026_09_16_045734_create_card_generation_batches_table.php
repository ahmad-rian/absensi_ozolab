<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_generation_batches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->constrained()->cascadeOnDelete();
            // Null berarti seluruh sekolah, bukan "kelas tidak diketahui".
            $table->foreignUlid('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Jumlah kartu yang diantrekan, bukan jumlah siswa: satu siswa
            // menghasilkan satu baris log per layout aktif.
            $table->unsignedInteger('total')->default(0);
            $table->string('status')->default('processing');
            $table->timestamps();

            $table->index(['school_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_generation_batches');
    }
};
