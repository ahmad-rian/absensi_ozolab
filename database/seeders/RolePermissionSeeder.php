<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

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
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => UserRole::Admin->value]);
        $admin->syncPermissions($permissions);

        $guru = Role::firstOrCreate(['name' => UserRole::Guru->value]);
        $guru->syncPermissions([
            'dashboard.view',
            'student.view',
            'attendance.view',
            'attendance.create',
            'classroom.view',
            'report.view',
        ]);

        $orangTua = Role::firstOrCreate(['name' => UserRole::OrangTua->value]);
        $orangTua->syncPermissions([
            'dashboard.view',
            'student.view',
            'attendance.view',
        ]);
    }
}
