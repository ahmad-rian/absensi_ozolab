<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_drive_configs', function (Blueprint $table) {
            $table->string('parents_folder_id')->nullable()->after('albums_folder_id');
        });
    }

    public function down(): void
    {
        Schema::table('school_drive_configs', function (Blueprint $table) {
            $table->dropColumn('parents_folder_id');
        });
    }
};
