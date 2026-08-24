<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case Pending = 'PENDING';
    case Sent = 'SENT';
    case Failed = 'FAILED';

    /**
     * Tidak dikirim karena kuota WhatsApp harian sekolah sudah habis.
     *
     * Dibedakan dari `Failed` supaya admin bisa memisahkan "gateway bermasalah"
     * dari "kita sendiri yang menahan". Keduanya sama-sama tidak sampai ke orang
     * tua, tapi obatnya berbeda.
     */
    case Throttled = 'THROTTLED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Sent => 'Terkirim',
            self::Failed => 'Gagal',
            self::Throttled => 'Dibatasi',
        };
    }
}
