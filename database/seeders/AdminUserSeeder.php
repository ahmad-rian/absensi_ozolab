<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $smpNusantara = School::where('slug', 'smp-nusantara')->firstOrFail();
        $sdMentari = School::where('slug', 'sd-mentari')->firstOrFail();

        // Super Admin — platform owner, no school_id (can access all schools)
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@absenku.test',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'phone' => '+6281200000000',
            'is_active' => true,
            'school_id' => $smpNusantara->id, // default to first school
        ]);
        $superAdmin->assignRole(UserRole::SuperAdmin->value);

        // Admin SMP Nusantara
        $adminSmp = User::create([
            'name' => 'Admin SMP Nusantara',
            'email' => 'admin@smpnusantara.test',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'phone' => '+6281234567890',
            'is_active' => true,
            'school_id' => $smpNusantara->id,
        ]);
        $adminSmp->assignRole(UserRole::Admin->value);

        // Admin SD Mentari
        $adminSd = User::create([
            'name' => 'Admin SD Mentari',
            'email' => 'admin@sdmentari.test',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'phone' => '+6281234567891',
            'is_active' => true,
            'school_id' => $sdMentari->id,
        ]);
        $adminSd->assignRole(UserRole::Admin->value);

        // Guru SMP Nusantara
        $teachers = [
            ['name' => 'Budi Santoso', 'email' => 'budi@sekolah.test'],
            ['name' => 'Siti Rahayu', 'email' => 'siti@sekolah.test'],
            ['name' => 'Ahmad Hidayat', 'email' => 'ahmad@sekolah.test'],
        ];

        foreach ($teachers as $teacher) {
            $user = User::create([
                'name' => $teacher['name'],
                'email' => $teacher['email'],
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'phone' => '+628'.fake()->numerify('##########'),
                'is_active' => true,
                'school_id' => $smpNusantara->id,
            ]);
            $user->assignRole(UserRole::Guru->value);
        }
    }
}
