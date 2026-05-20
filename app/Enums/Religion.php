<?php

namespace App\Enums;

enum Religion: string
{
    case Islam = 'ISLAM';
    case Kristen = 'KRISTEN';
    case Katolik = 'KATOLIK';
    case Hindu = 'HINDU';
    case Buddha = 'BUDDHA';
    case Konghucu = 'KONGHUCU';

    public function label(): string
    {
        return match ($this) {
            self::Islam => 'Islam',
            self::Kristen => 'Kristen Protestan',
            self::Katolik => 'Katolik',
            self::Hindu => 'Hindu',
            self::Buddha => 'Buddha',
            self::Konghucu => 'Konghucu',
        };
    }
}
