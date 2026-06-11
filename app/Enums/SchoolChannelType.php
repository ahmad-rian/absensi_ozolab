<?php

namespace App\Enums;

enum SchoolChannelType: string
{
    case OzolabWa = 'OZOLAB_WA';
    case FonnteWa = 'FONNTE_WA';
    case Telegram = 'TELEGRAM';
    case Email = 'EMAIL';

    public function label(): string
    {
        return match ($this) {
            self::OzolabWa => 'WhatsApp (Ozolab ID)',
            self::FonnteWa => 'WhatsApp (Fonnte)',
            self::Telegram => 'Telegram',
            self::Email => 'Email',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::OzolabWa => 'Gateway WhatsApp default Ozolab ID. Tanpa kredensial khusus.',
            self::FonnteWa => 'Nomor WhatsApp khusus sekolah via Fonnte. Perlu token API.',
            self::Telegram => 'Push notifikasi via bot Telegram ke chat_id tiap orang tua.',
            self::Email => 'Kirim notifikasi via email ke alamat email orang tua. Memakai pengirim global (1 email untuk semua sekolah).',
        };
    }

    /**
     * Notification channel yang dipakai untuk logging.
     */
    public function notificationChannel(): NotificationChannel
    {
        return match ($this) {
            self::OzolabWa, self::FonnteWa => NotificationChannel::Whatsapp,
            self::Telegram => NotificationChannel::Telegram,
            self::Email => NotificationChannel::Email,
        };
    }
}
