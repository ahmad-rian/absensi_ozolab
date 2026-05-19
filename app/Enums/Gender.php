<?php

namespace App\Enums;

enum Gender: string
{
    case LakiLaki = 'LAKI_LAKI';
    case Perempuan = 'PEREMPUAN';

    public function label(): string
    {
        return match ($this) {
            self::LakiLaki => 'Laki-laki',
            self::Perempuan => 'Perempuan',
        };
    }
}
