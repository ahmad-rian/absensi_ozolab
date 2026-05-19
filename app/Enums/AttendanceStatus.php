<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Hadir = 'HADIR';
    case Terlambat = 'TERLAMBAT';
    case Alpa = 'ALPA';
    case Izin = 'IZIN';
    case Sakit = 'SAKIT';

    public function label(): string
    {
        return match ($this) {
            self::Hadir => 'Hadir',
            self::Terlambat => 'Terlambat',
            self::Alpa => 'Alpa',
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Hadir => 'success',
            self::Terlambat => 'warning',
            self::Alpa => 'destructive',
            self::Izin => 'secondary',
            self::Sakit => 'default',
        };
    }
}
