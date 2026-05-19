<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'ADMIN';
    case Guru = 'GURU';
    case OrangTua = 'ORANG_TUA';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Guru => 'Guru',
            self::OrangTua => 'Orang Tua',
        };
    }
}
