<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // null = ikut aturan sekolah, true = diikutkan, false = dikecualikan.
            // Sengaja nullable supaya seluruh data lama tidak berubah perilaku.
            $table->boolean('prayer_opt_in')->nullable()->after('religion');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('prayer_opt_in');
        });
    }
};
