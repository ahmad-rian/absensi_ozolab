<?php

use App\Models\School;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('scanner_token', 64)->nullable()->unique()->after('slug');
        });

        School::withTrashed()->whereNull('scanner_token')->get()->each(function (School $school) {
            $school->update(['scanner_token' => Str::random(40)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropUnique(['scanner_token']);
            $table->dropColumn('scanner_token');
        });
    }
};
