<?php

namespace App\Services;

use App\Enums\ParentRelation;
use App\Enums\UserRole;
use App\Models\ParentProfile;
use App\Models\User;
use App\Support\AlamatLoginOrangTua;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ParentProfileService
{
    public function findOrCreateFromRegistration(string $schoolId, string $parentName, string $parentPhone, string $relation = 'WALI', ?string $email = null): ParentProfile
    {
        $phone = trim($parentPhone);

        // Pertahanan berlapis: alamat ber-CR/LF pernah lolos rule `email` dan
        // berakhir mentah di perintah SMTP `RCPT TO`.
        $email = $email ? trim($email) : null;
        if ($email !== null && preg_match('/[\r\n]/', $email)) {
            $email = null;
        }

        $existing = ParentProfile::withoutGlobalScope('school')->where('school_id', $schoolId)
            ->where('whatsapp_number', $phone)
            ->first();

        if ($email) {
            $owner = User::withTrashed()->where('email', $email)->first();
            if ($owner && $owner->id !== $existing?->user_id) {
                throw ValidationException::withMessages(['parent_email' => $owner->school_id !== $schoolId
                    ? 'Email sudah dipakai akun orang tua di sekolah lain.' : 'Email sudah dipakai akun lain.']);
            }
        }
        if ($existing) {
            if ($email && AlamatLoginOrangTua::bawaanSistem($existing->user->email)) {
                $existing->user->update(['email' => $email]);
            }
            // Lengkapi email notifikasi jika sebelumnya kosong.
            if ($email && empty($existing->email)) {
                $existing->update(['email' => $email]);
            }

            return $existing;
        }

        $user = User::create([
            'name' => $parentName,
            'email' => $email ?: AlamatLoginOrangTua::untuk($parentName),
            'password' => Hash::make('password'),
            'must_change_password' => true,
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
