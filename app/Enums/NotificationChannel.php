<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case Whatsapp = 'WHATSAPP';
    case Email = 'EMAIL';

    public function label(): string
    {
        return match ($this) {
            self::Whatsapp => 'WhatsApp',
            self::Email => 'Email',
        };
    }
}
