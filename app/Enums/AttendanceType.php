<?php

namespace App\Enums;

enum AttendanceType: string
{
    case CheckIn = 'CHECK_IN';
    case CheckOut = 'CHECK_OUT';

    public function label(): string
    {
        return match ($this) {
            self::CheckIn => 'Check In',
            self::CheckOut => 'Check Out',
        };
    }
}
