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
        $school = School::where('slug', 'smp-nusantara')->firstOrFail();

        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin@sekolah.test',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'phone' => '+6281234567890',
            'is_active' => true,
            'school_id' => $school->id,
        ]);
        $admin->assignRole(UserRole::Admin->value);

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
                'phone' => '+628' . fake()->numerify('##########'),
                'is_active' => true,
                'school_id' => $school->id,
            ]);
            $user->assignRole(UserRole::Guru->value);
        }
    }
}
