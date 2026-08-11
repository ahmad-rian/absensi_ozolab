<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Unique global, mengikuti pola nis/nisn/qr_token di tabel ini. UID
            // kartu memang unik dari pabrik, jadi satu kartu tidak boleh menempel
            // ke dua siswa — termasuk lintas sekolah.
            $table->string('rfid_uid', 64)->nullable()->unique()->after('qr_rotated_at');
            $table->timestamp('rfid_registered_at')->nullable()->after('rfid_uid');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['rfid_uid']);
            $table->dropColumn(['rfid_uid', 'rfid_registered_at']);
        });
    }
};
