<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',
            'student.view',
            'student.create',
            'student.update',
            'student.delete',
            'attendance.view',
            'attendance.create',
            'attendance.export',
            'classroom.view',
            'classroom.manage',
            'report.view',
            'setting.manage',
            'user.view',
            'user.create',
            'user.update',
            'user.delete',
            'school.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Super Admin — platform owner, all permissions, all schools
        $superAdmin = Role::firstOrCreate(['name' => UserRole::SuperAdmin->value]);
        $superAdmin->syncPermissions($permissions);

        // Admin — school admin, all permissions within their school
        $admin = Role::firstOrCreate(['name' => UserRole::Admin->value]);
        $admin->syncPermissions($permissions);

        // Guru — operator, limited access
        $guru = Role::firstOrCreate(['name' => UserRole::Guru->value]);
        $guru->syncPermissions([
            'dashboard.view',
            'student.view',
            'attendance.view',
            'attendance.create',
            'classroom.view',
            'report.view',
        ]);

        // Orang Tua — parent, view only
        $orangTua = Role::firstOrCreate(['name' => UserRole::OrangTua->value]);
        $orangTua->syncPermissions([
            'dashboard.view',
            'student.view',
            'attendance.view',
        ]);
    }
}
