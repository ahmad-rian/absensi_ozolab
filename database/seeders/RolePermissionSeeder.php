<?php

namespace Database\Seeders;

use App\Enums\AppModule;
use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempoten dan aman dijalankan ulang di produksi.
 *
 * Hanya 4 role bawaan yang di-sync; role custom buatan admin tidak disentuh.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = AppModule::permissions();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Buang permission per-aksi versi lama (student.view, attendance.create, …)
        Permission::whereNotIn('name', $permissions)->delete();

        foreach (UserRole::cases() as $role) {
            $modules = AppModule::defaultsFor($role);

            Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web'])
                ->syncPermissions(array_map(fn (AppModule $m) => $m->permission(), $modules));
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
