<?php

namespace App\Enums;

enum ParentRelation: string
{
    case Ayah = 'AYAH';
    case Ibu = 'IBU';
    case Wali = 'WALI';

    public function label(): string
    {
        return match ($this) {
            self::Ayah => 'Ayah',
            self::Ibu => 'Ibu',
            self::Wali => 'Wali',
        };
    }
}
