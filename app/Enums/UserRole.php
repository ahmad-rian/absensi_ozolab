<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'SUPER_ADMIN';
    case Admin = 'ADMIN';
    case Guru = 'GURU';
    case OrangTua = 'ORANG_TUA';
    case AdminPenyelenggara = 'ADMIN_PENYELENGGARA';
    case Pendamping = 'PENDAMPING';
    case StafGeneral = 'STAF_GENERAL';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin Sekolah',
            self::Guru => 'Guru',
            self::OrangTua => 'Orang Tua',
            self::AdminPenyelenggara => 'Admin Penyelenggara',
            self::Pendamping => 'Pendamping',
            self::StafGeneral => 'Staf General',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Pemilik platform, akses semua sekolah',
            self::Admin => 'Administrator sekolah, kelola data sekolah',
            self::Guru => 'Guru/operator absensi',
            self::OrangTua => 'Orang tua/wali siswa',
            self::AdminPenyelenggara => 'Administrator penyelenggara (biro, panitia), kelola data penyelenggaranya',
            self::Pendamping => 'Pendamping/muthawif, mencatat kehadiran grup yang ditugaskan',
            self::StafGeneral => 'Staf Tyas di workspace Absensi General; modulnya dicentang per akun',
        };
    }

    /**
     * Peran milik tenant penyelenggara — bukan sekolah.
     *
     * @return array<int, self>
     */
    public static function penyelenggaraRoles(): array
    {
        return [self::AdminPenyelenggara, self::Pendamping];
    }

    public function isPenyelenggaraRole(): bool
    {
        return in_array($this, self::penyelenggaraRoles(), true);
    }
}
