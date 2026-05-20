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
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('parent_profile_id')->nullable()->change();
            $table->string('parent_name')->nullable()->after('address');
            $table->string('parent_phone')->nullable()->after('parent_name');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['parent_name', 'parent_phone']);
            $table->foreignId('parent_profile_id')->nullable(false)->change();
        });
    }
};
