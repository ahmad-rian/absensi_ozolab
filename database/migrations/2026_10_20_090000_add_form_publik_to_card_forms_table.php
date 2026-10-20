<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saklar form pendaftaran publik (`/f/{token}`) untuk layout program
 * penyelenggara. NULL = perilaku lama: form Haji/Kartu Bebas tetap publik
 * selama aktif, form program tertutup sampai dinyalakan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_forms', function (Blueprint $table) {
            $table->boolean('form_publik')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('card_forms', function (Blueprint $table) {
            $table->dropColumn('form_publik');
        });
    }
};
