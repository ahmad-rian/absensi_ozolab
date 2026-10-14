<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->string('scanner_token', 64)->nullable()->unique();
        });

        // Tabel baru (spec 002): mengisi token program yang sudah ada tidak
        // menyentuh data Kartu Bebas/haji.
        DB::table('programs')->whereNull('scanner_token')->orderBy('id')->each(function ($row) {
            DB::table('programs')->where('id', $row->id)->update(['scanner_token' => Str::random(48)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropUnique(['scanner_token']);
            $table->dropColumn('scanner_token');
        });
    }
};
