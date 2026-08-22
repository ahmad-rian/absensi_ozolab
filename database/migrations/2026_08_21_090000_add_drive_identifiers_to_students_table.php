<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tautan Drive milik siswa, disimpan alih-alih ditebak ulang.
 *
 * Sampai sekarang tidak ada satu pun kolom Drive di tabel ini. Letak foto seorang
 * siswa diturunkan kembali setiap kali dibutuhkan dari jalur folder
 * `{Sekolah}/{Kelas}/{NIS - Nama}` — semuanya nilai yang bisa berubah. Naik kelas,
 * pindah rombel, NIS diperbaiki, atau nama diseragamkan huruf besar (commit
 * 874f1dd melakukan itu ke seluruh tabel) memindahkan sasaran pencarian ke folder
 * lain, dan operator melihatnya sebagai "foto anaknya hilang" atau "URL-nya
 * ganti sendiri".
 *
 * Id berkas Drive tidak ikut berubah saat berkasnya dipindah atau diganti nama,
 * jadi menyimpannya adalah satu-satunya tautan yang tetap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Nama berkas yang diketik pendaftar. Selama ini hanya lewat sebagai
            // field form lalu dibuang, sehingga foto tidak pernah bisa diambil
            // ulang tanpa bertanya lagi ke orang tuanya.
            $table->string('photo_drive_filename', 500)->nullable()->after('photo_path');
            $table->string('photo_drive_file_id')->nullable()->after('photo_drive_filename');
            $table->string('drive_folder_id')->nullable()->after('photo_drive_file_id');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['photo_drive_filename', 'photo_drive_file_id', 'drive_folder_id']);
        });
    }
};
