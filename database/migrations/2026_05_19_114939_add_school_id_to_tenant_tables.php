<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'users',
        'classrooms',
        'students',
        'academic_years',
        'attendance_schedules',
        'attendances',
        'notification_logs',
        'parent_profiles',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignUlid('school_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('schools')
                    ->nullOnDelete();

                $table->index('school_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('school_id');
            });
        }
    }
};
