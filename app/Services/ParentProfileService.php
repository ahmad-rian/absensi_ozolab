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
    public function findOrCreateFromRegistration(string $schoolId, string $parentName, string $parentPhone, string $relation = 'WALI', ?string $email = null, ?string $password = null): ParentProfile
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
            if ($password !== null && (! $existing->user
                || ! Hash::check($password, $existing->user->password)
                || (! AlamatLoginOrangTua::bawaanSistem($existing->user->email) && $existing->user->email !== $email))) {
                throw ValidationException::withMessages(['password' => 'Nomor WhatsApp sudah terhubung ke akun orang tua. Gunakan email dan kata sandi akun tersebut.']);
            }

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
            'password' => Hash::make($password ?? '11111111'),
            'must_change_password' => $password === null,
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
