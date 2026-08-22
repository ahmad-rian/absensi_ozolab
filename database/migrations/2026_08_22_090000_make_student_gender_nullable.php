<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis kelamin boleh kosong.
 *
 * `/daftar-cepat` hanya menanyakan empat hal — nama, nomor foto, kelas, nomor
 * absen — karena ia dipakai di depan antrean sesi foto, tempat satu-satunya yang
 * ada cuma daftar hadir. Kolomnya NOT NULL sejak tabel ini dibuat, jadi tanpa
 * migrasi ini pendaftaran cepat mustahil disimpan.
 *
 * Semua pembaca yang menurunkan nilainya (`$student->gender->value`) sudah
 * diberi penjagaan lebih dulu — lihat StudentApiController. Jalur admin dan
 * impor tetap mewajibkannya: yang berubah adalah batas bawah basis data, bukan
 * aturan pengisian di form yang memang punya datanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('gender')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Sengaja tidak mengisi baris yang sudah kosong: menebak jenis kelamin
        // seseorang lebih buruk daripada membiarkan rollback ini gagal keras.
        Schema::table('students', function (Blueprint $table) {
            $table->string('gender')->nullable(false)->change();
        });
    }
};
