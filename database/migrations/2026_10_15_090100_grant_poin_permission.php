<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Poin Siswa: Admin sekolah (guru BK) melihat dan mengelola, Guru hanya
 * melihat. Diberikan di sini supaya prod tidak menjalankan RolePermissionSeeder
 * penuh (yang mereset hak akses peran bawaan).
 */
return new class extends Migration
{
    public function up(): void
    {
        $lihat = Permission::firstOrCreate(['name' => 'poin.access', 'guard_name' => 'web']);
        $kelola = Permission::firstOrCreate(['name' => 'poin-kelola.access', 'guard_name' => 'web']);

        Role::firstOrCreate(['name' => 'ADMIN', 'guard_name' => 'web'])->givePermissionTo([$lihat, $kelola]);
        Role::firstOrCreate(['name' => 'GURU', 'guard_name' => 'web'])->givePermissionTo($lihat);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', ['poin.access', 'poin-kelola.access'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
