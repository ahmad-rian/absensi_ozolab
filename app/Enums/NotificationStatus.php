<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case Pending = 'PENDING';
    case Sent = 'SENT';
    case Failed = 'FAILED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Sent => 'Terkirim',
            self::Failed => 'Gagal',
        };
    }
}
