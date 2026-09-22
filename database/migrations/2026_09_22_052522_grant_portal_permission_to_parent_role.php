<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'portal-orang-tua.access', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'ORANG_TUA', 'guard_name' => 'web'])->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'portal-orang-tua.access')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
