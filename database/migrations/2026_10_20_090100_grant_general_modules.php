<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Modul workspace General untuk akun staf Tyas (peran STAF_GENERAL). Peran
 * ini sengaja tanpa permission: modulnya dicentang per akun. Super admin
 * sudah lolos semua lewat Gate::before.
 */
return new class extends Migration
{
    private const MODUL = [
        'general-ringkasan.access',
        'general-penyelenggara.access',
        'general-katalog.access',
        'general-haji.access',
        'general-pengguna.access',
        'general-pengaturan.access',
    ];

    public function up(): void
    {
        foreach (self::MODUL as $nama) {
            Permission::firstOrCreate(['name' => $nama, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'STAF_GENERAL', 'guard_name' => 'web']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', self::MODUL)->delete();
        Role::where('name', 'STAF_GENERAL')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
