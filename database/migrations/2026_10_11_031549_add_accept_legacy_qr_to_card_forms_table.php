<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saklar per layout: terima kartu lama yang QR-nya berisi kolom data
 * (No Porsi) alih-alih token absensi bertanda tangan. Untuk layout yang
 * kartunya sudah terlanjur dicetak massal sebelum Okt 2026. Bawaan mati.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_forms', function (Blueprint $table) {
            $table->boolean('accept_legacy_qr')->default(false)->after('scanner_token');
        });
    }

    public function down(): void
    {
        Schema::table('card_forms', function (Blueprint $table) {
            $table->dropColumn('accept_legacy_qr');
        });
    }
};
