<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel terpisah dari `attendances` — bukan `type` baru — karena banyak
     * query dashboard/laporan tidak memfilter `type`, sehingga baris sholat
     * akan menggelembungkan statistik absensi sekolah.
     */
    public function up(): void
    {
        Schema::create('prayer_attendances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('student_id')->constrained()->cascadeOnDelete();
            $table->date('prayer_date')->index();
            $table->string('status')->default('HADIR');
            $table->timestamp('recorded_at');
            $table->foreignUlid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('device_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Sekali check-in per siswa per hari.
            $table->unique(['student_id', 'prayer_date']);
            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prayer_attendances');
    }
};
