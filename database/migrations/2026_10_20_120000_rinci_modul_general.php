<?php

use App\Enums\ModulGeneral;
use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Modul General dipecah per menu (6 → 19) dan peran staf custom.
 *
 * Pemegang modul lama otomatis mendapat semua modul rinci penggantinya, jadi
 * tidak ada staf yang kehilangan akses. `roles.lingkup = 'general'` menandai
 * peran staf buatan super admin (template modul).
 */
return new class extends Migration
{
    private const PETA = [
        'general-ringkasan.access' => ['ringkasan'],
        'general-penyelenggara.access' => ['penyelenggara', 'penyelenggara-masuk'],
        'general-katalog.access' => ['kategori', 'program'],
        'general-haji.access' => ['haji-dashboard', 'haji-peserta', 'haji-format', 'haji-absensi', 'haji-laporan', 'haji-generate', 'desain-frame', 'desain-layout', 'desain-riwayat'],
        'general-pengguna.access' => ['pengguna-staf', 'pengguna-penyelenggara'],
        'general-pengaturan.access' => ['pengaturan-umum', 'pengaturan-drive', 'pengaturan-whatsapp'],
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('roles', 'lingkup')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->string('lingkup', 20)->nullable()->after('guard_name');
            });
        }

        $baru = [];
        foreach (ModulGeneral::cases() as $modul) {
            $baru[$modul->value] = Permission::firstOrCreate(['name' => $modul->permission(), 'guard_name' => 'web'])->id;
        }

        foreach (self::PETA as $lama => $pengganti) {
            $lamaId = Permission::where('name', $lama)->value('id');

            if (! $lamaId) {
                continue;
            }

            foreach (DB::table('model_has_permissions')->where('permission_id', $lamaId)->get() as $baris) {
                foreach ($pengganti as $kunci) {
                    DB::table('model_has_permissions')->insertOrIgnore([
                        'permission_id' => $baru[$kunci],
                        'model_type' => $baris->model_type,
                        'model_id' => $baris->model_id,
                    ]);
                }
            }

            foreach (DB::table('role_has_permissions')->where('permission_id', $lamaId)->get() as $baris) {
                foreach ($pengganti as $kunci) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $baru[$kunci], 'role_id' => $baris->role_id]);
                }
            }

            Permission::whereKey($lamaId)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Modul rinci dibiarkan: menghapusnya mencabut akses staf yang sedang bekerja.
        if (Schema::hasColumn('roles', 'lingkup')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('lingkup');
            });
        }
    }
};
