<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert all ID columns from bigint to char(26) for ULID support.
     * Drops FK constraints, modifies columns, then re-creates FKs.
     */
    public function up(): void
    {
        // Skip if already converted
        $type = DB::selectOne("SHOW COLUMNS FROM students WHERE Field = 'id'")?->Type ?? '';
        if (! str_contains($type, 'bigint')) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        // 1. Drop ALL foreign key constraints
        $fks = $this->getAllForeignKeys();
        foreach ($fks as $fk) {
            $this->dropForeignKeyIfExists($fk['table'], $fk['name']);
        }

        // 2. Convert all primary key ID columns
        $primaryTables = [
            'users', 'schools', 'academic_years', 'classrooms', 'students',
            'parent_profiles', 'attendances', 'attendance_schedules',
            'notification_logs', 'settings', 'school_frames',
            'school_card_layouts', 'card_generation_logs', 'school_drive_configs',
            'permissions', 'roles',
        ];
        if (Schema::hasTable('school_album_layouts')) {
            $primaryTables[] = 'school_album_layouts';
        }

        foreach ($primaryTables as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `id` CHAR(26) NOT NULL");
            }
        }

        // 3. Convert all foreign key columns
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
            'model_has_permissions' => ['permission_id'],
            'model_has_roles' => ['role_id'],
            'role_has_permissions' => ['permission_id', 'role_id'],
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

        // 4. Morph columns (polymorphic)
        foreach (['model_has_permissions', 'model_has_roles'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'model_morph_key')) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `model_morph_key` VARCHAR(26) NOT NULL");
            }
        }

        if (Schema::hasTable('activity_log')) {
            foreach (['subject_id', 'causer_id'] as $col) {
                if (Schema::hasColumn('activity_log', $col)) {
                    DB::statement("ALTER TABLE `activity_log` MODIFY `{$col}` VARCHAR(26) NULL");
                }
            }
        }

        // 5. Re-create all foreign key constraints
        foreach ($fks as $fk) {
            if (Schema::hasTable($fk['table']) && Schema::hasTable($fk['ref_table'])) {
                $this->addForeignKeyIfNotExists(
                    $fk['table'], $fk['column'], $fk['ref_table'], $fk['ref_column'], $fk['on_delete']
                );
            }
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Get all foreign key constraints to drop and re-create.
     *
     * @return array<int, array{table: string, name: string, column: string, ref_table: string, ref_column: string, on_delete: string}>
     */
    private function getAllForeignKeys(): array
    {
        $dbName = config('database.connections.mysql.database');

        $rows = DB::select('
            SELECT
                TABLE_NAME as `table_name`,
                CONSTRAINT_NAME as `constraint_name`,
                COLUMN_NAME as `column_name`,
                REFERENCED_TABLE_NAME as `ref_table`,
                REFERENCED_COLUMN_NAME as `ref_column`
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY TABLE_NAME
        ', [$dbName]);

        $fks = [];
        foreach ($rows as $row) {
            // Get ON DELETE rule
            $rule = DB::selectOne('
                SELECT DELETE_RULE
                FROM information_schema.REFERENTIAL_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = ?
                  AND CONSTRAINT_NAME = ?
                  AND TABLE_NAME = ?
            ', [$dbName, $row->constraint_name, $row->table_name]);

            $fks[] = [
                'table' => $row->table_name,
                'name' => $row->constraint_name,
                'column' => $row->column_name,
                'ref_table' => $row->ref_table,
                'ref_column' => $row->ref_column,
                'on_delete' => strtolower($rule?->DELETE_RULE ?? 'restrict'),
            ];
        }

        return $fks;
    }

    private function dropForeignKeyIfExists(string $table, string $name): void
    {
        try {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
        } catch (Throwable) {
            // Already dropped
        }
    }

    private function addForeignKeyIfNotExists(string $table, string $column, string $refTable, string $refColumn, string $onDelete): void
    {
        $onDeleteSql = match ($onDelete) {
            'cascade' => 'CASCADE',
            'set null' => 'SET NULL',
            'no action' => 'NO ACTION',
            default => 'RESTRICT',
        };

        $fkName = "{$table}_{$column}_foreign";

        try {
            DB::statement("
                ALTER TABLE `{$table}`
                ADD CONSTRAINT `{$fkName}`
                FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}`(`{$refColumn}`)
                ON DELETE {$onDeleteSql}
            ");
        } catch (Throwable $e) {
            // Log but don't fail - FK might already exist with different name
            logger()->warning("Could not re-create FK {$fkName}: ".$e->getMessage());
        }
    }

    public function down(): void
    {
        // Not reversible - would lose ULID data
    }
};
