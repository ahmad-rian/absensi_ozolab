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
        Schema::table('card_forms', function (Blueprint $table) {
            $table->string('scanner_token', 64)->nullable()->unique();
        });
        DB::table('card_forms')->select('id')->orderBy('id')->chunkById(100, function ($forms) {
            foreach ($forms as $form) {
                DB::table('card_forms')->where('id', $form->id)->update(['scanner_token' => Str::random(48)]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('card_forms', function (Blueprint $table) {
            $table->dropUnique(['scanner_token']);
            $table->dropColumn('scanner_token');
        });
    }
};
