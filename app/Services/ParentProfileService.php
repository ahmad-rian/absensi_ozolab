<?php

namespace App\Services;

use App\Enums\ParentRelation;
use App\Enums\UserRole;
use App\Models\ParentProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ParentProfileService
{
    public function findOrCreateFromRegistration(string $schoolId, string $parentName, string $parentPhone, string $relation = 'WALI', ?string $email = null): ParentProfile
    {
        $phone = trim($parentPhone);
        $email = $email ? trim($email) : null;

        $existing = ParentProfile::where('school_id', $schoolId)
            ->where('whatsapp_number', $phone)
            ->first();

        if ($existing) {
            // Lengkapi email notifikasi jika sebelumnya kosong.
            if ($email && empty($existing->email)) {
                $existing->update(['email' => $email]);
            }

            return $existing;
        }

        $user = User::create([
            'name' => $parentName,
            'email' => 'parent-'.Str::ulid().'@internal.app',
            'password' => Hash::make(Str::random(16)),
            'phone' => $phone,
            'school_id' => $schoolId,
        ]);

        $user->assignRole(UserRole::OrangTua);

        return $user->parentProfile()->create([
            'school_id' => $schoolId,
            'whatsapp_number' => $phone,
            'email' => $email,
            'relation' => ParentRelation::from($relation),
        ]);
    }
}
