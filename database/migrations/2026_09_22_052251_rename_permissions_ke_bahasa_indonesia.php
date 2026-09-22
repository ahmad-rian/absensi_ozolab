<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const NAMES = [
        'dashboard.access' => 'beranda.access',
        'rfid-cards.access' => 'kartu-rfid.access',
        'frames.access' => 'bingkai.access',
        'card-layouts.access' => 'layout-kartu.access',
        'card-generation.access' => 'generate-kartu.access',
        'album-layouts.access' => 'layout-album.access',
        'album-generation.access' => 'generate-album.access',
        'photo-sheets.access' => 'pas-foto.access',
        'users.access' => 'pengguna.access',
        'drive-config.access' => 'google-drive.access',
        'wa-config.access' => 'whatsapp.access',
        'roles.access' => 'hak-akses.access',
        'schools.access' => 'sekolah.access',
        'notification-gateways.access' => 'gateway-notifikasi.access',
        'card-forms.access' => 'form-kartu.access',
        'impersonate.access' => 'masuk-sebagai.access',
    ];

    public function up(): void
    {
        $this->rename(self::NAMES);
    }

    public function down(): void
    {
        $this->rename(array_flip(self::NAMES));
    }

    private function rename(array $names): void
    {
        foreach ($names as $old => $new) {
            foreach (DB::table('permissions')->where('name', $old)->get() as $permission) {
                if (! DB::table('permissions')->where('name', $new)->where('guard_name', $permission->guard_name)->exists()) {
                    DB::table('permissions')->where('id', $permission->id)->update(['name' => $new]);
                }
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
