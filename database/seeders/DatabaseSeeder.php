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
            AdminUserSeeder::class,
            // AcademicYearSeeder::class,
            // ClassroomSeeder::class,
            // AttendanceScheduleSeeder::class,
            // ParentAndStudentSeeder::class,
            // AttendanceHistorySeeder::class,
            // NotificationLogSeeder::class,
            // StudentPhotoSeeder::class,
        ]);
    }
}
