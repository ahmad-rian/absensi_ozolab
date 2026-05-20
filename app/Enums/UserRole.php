<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'SUPER_ADMIN';
    case Admin = 'ADMIN';
    case Guru = 'GURU';
    case OrangTua = 'ORANG_TUA';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin Sekolah',
            self::Guru => 'Guru',
            self::OrangTua => 'Orang Tua',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Pemilik platform, akses semua sekolah',
            self::Admin => 'Administrator sekolah, kelola data sekolah',
            self::Guru => 'Guru/operator absensi',
            self::OrangTua => 'Orang tua/wali siswa',
        };
    }
}
