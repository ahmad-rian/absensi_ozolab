<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SettingSeeder::class,
            SchoolSeeder::class,
            AcademicYearSeeder::class,
            AdminUserSeeder::class,
            ClassroomSeeder::class,
            AttendanceScheduleSeeder::class,
            ParentAndStudentSeeder::class,
            AttendanceHistorySeeder::class,
            NotificationLogSeeder::class,
        ]);
    }
}
