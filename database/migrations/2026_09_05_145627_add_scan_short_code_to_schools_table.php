<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            // Alias `/g/{kode}` yang bisa dinamai sendiri, mis. "tyas-photo".
            // Kosong = pakai 8 karakter pertama scanner_token sebagai cadangan,
            // jadi sekolah yang tidak pernah diatur tetap punya alamat pendek.
            //
            // Unik global: satu alias tidak boleh menunjuk dua sekolah. Disimpan
            // huruf kecil semua supaya "Tyas" dan "tyas" tidak jadi dua baris
            // yang berebut alamat sama.
            $table->string('scan_short_code', 32)->nullable()->unique()->after('scanner_token');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropUnique(['scan_short_code']);
            $table->dropColumn('scan_short_code');
        });
    }
};
