<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Menu Data Perizinan untuk Admin sekolah dan Guru. Diberikan di sini supaya
 * prod tidak perlu menjalankan RolePermissionSeeder penuh (yang mereset hak
 * akses peran bawaan).
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'perizinan.access', 'guard_name' => 'web']);

        foreach (['ADMIN', 'GURU'] as $peran) {
            Role::firstOrCreate(['name' => $peran, 'guard_name' => 'web'])->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'perizinan.access')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
