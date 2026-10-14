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
        // Audit 2026-10-14: parent_id restrict + program_id cascade membuat
        // penghapusan program bisa gagal (InnoDB 1451) saat induk dihapus lebih
        // dulu dari anaknya. Hanya tabel baru `grups`; data lama tidak disentuh.
        Schema::table('grups', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->foreign('parent_id')->references('id')->on('grups')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grups', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->foreign('parent_id')->references('id')->on('grups')->restrictOnDelete();
        });
    }
};
