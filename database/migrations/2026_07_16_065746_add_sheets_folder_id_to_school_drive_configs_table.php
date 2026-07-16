<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('school_drive_configs', function (Blueprint $table) {
            $table->string('sheets_folder_id')->nullable()->after('parents_folder_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_drive_configs', function (Blueprint $table) {
            $table->dropColumn('sheets_folder_id');
        });
    }
};
