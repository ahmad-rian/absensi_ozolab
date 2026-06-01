<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert all ID columns from bigint to char(26) for ULID support.
     * Only runs if columns are still bigint (idempotent).
     */
    public function up(): void
    {
        // Skip if already converted
        $type = DB::selectOne("SHOW COLUMNS FROM students WHERE Field = 'id'")?->Type ?? '';
        if (! str_contains($type, 'bigint')) {
            return;
        }

        // Disable FK checks for the conversion
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            // 1. Primary key tables - convert id columns
            $primaryTables = [
                'users',
                'schools',
                'academic_years',
                'classrooms',
                'students',
                'parent_profiles',
                'attendances',
                'attendance_schedules',
                'notification_logs',
                'settings',
                'school_frames',
                'school_card_layouts',
                'card_generation_logs',
                'school_drive_configs',
            ];

            // Add school_album_layouts only if it exists
            if (Schema::hasTable('school_album_layouts')) {
                $primaryTables[] = 'school_album_layouts';
            }

            foreach ($primaryTables as $table) {
                if (Schema::hasTable($table)) {
                    DB::statement("ALTER TABLE `{$table}` MODIFY `id` CHAR(26) NOT NULL");
                }
            }

            // 2. Foreign key columns - convert to char(26)
            $foreignColumns = [
                'sessions' => ['user_id'],
                'classrooms' => ['academic_year_id', 'homeroom_teacher_id', 'school_id'],
                'parent_profiles' => ['user_id', 'school_id'],
                'students' => ['parent_profile_id', 'classroom_id', 'school_id'],
                'attendance_schedules' => ['classroom_id', 'school_id'],
                'attendances' => ['student_id', 'recorded_by', 'school_id'],
                'notification_logs' => ['student_id', 'attendance_id', 'parent_profile_id', 'school_id'],
                'school_drive_configs' => ['school_id'],
                'school_frames' => ['school_id'],
                'school_card_layouts' => ['school_id'],
                'card_generation_logs' => ['school_id', 'student_id', 'school_card_layout_id'],
            ];

            if (Schema::hasTable('school_album_layouts')) {
                $foreignColumns['school_album_layouts'] = ['school_id'];
            }

            foreach ($foreignColumns as $table => $columns) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                foreach ($columns as $col) {
                    if (Schema::hasColumn($table, $col)) {
                        DB::statement("ALTER TABLE `{$table}` MODIFY `{$col}` CHAR(26) NULL");
                    }
                }
            }

            // 3. Spatie permission tables - morph columns
            $morphTables = ['model_has_permissions', 'model_has_roles'];
            foreach ($morphTables as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'model_morph_key')) {
                    DB::statement("ALTER TABLE `{$table}` MODIFY `model_morph_key` VARCHAR(26) NOT NULL");
                }
            }

            // 4. Permission & role tables
            foreach (['permissions', 'roles'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::statement("ALTER TABLE `{$table}` MODIFY `id` CHAR(26) NOT NULL");
                }
            }

            // FK columns in permission tables
            $permFkColumns = [
                'model_has_permissions' => ['permission_id'],
                'model_has_roles' => ['role_id'],
                'role_has_permissions' => ['permission_id', 'role_id'],
            ];
            foreach ($permFkColumns as $table => $columns) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                foreach ($columns as $col) {
                    if (Schema::hasColumn($table, $col)) {
                        DB::statement("ALTER TABLE `{$table}` MODIFY `{$col}` CHAR(26) NOT NULL");
                    }
                }
            }

            // 5. Activity log table
            if (Schema::hasTable('activity_log')) {
                foreach (['subject_id', 'causer_id'] as $col) {
                    if (Schema::hasColumn('activity_log', $col)) {
                        DB::statement("ALTER TABLE `activity_log` MODIFY `{$col}` VARCHAR(26) NULL");
                    }
                }
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversible - would lose ULID data
    }
};
