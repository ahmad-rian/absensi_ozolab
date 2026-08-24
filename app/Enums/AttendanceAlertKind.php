<?php

namespace App\Enums;

/**
 * Kejadian absensi yang layak diberitahukan ke orang tua lewat WhatsApp.
 *
 * Sengaja BUKAN AttendanceStatus, walau dua nilainya kebetulan bernama sama:
 * AttendanceStatus menjawab "apa yang tercatat", enum ini menjawab "apa yang
 * dikabarkan". `IZIN` dan `SAKIT` adalah ketidakhadiran berizin dan tidak
 * pernah jadi peringatan, sementara `ALPA` di sini juga mencakup siswa yang
 * sama sekali tidak punya baris absensi — keadaan yang tidak bisa diwakili
 * satu pun nilai AttendanceStatus.
 */
enum AttendanceAlertKind: string
{
    case Terlambat = 'TERLAMBAT';
    case Alpa = 'ALPA';

    public function label(): string
    {
        return match ($this) {
            self::Terlambat => 'Terlambat',
            self::Alpa => 'Tidak Hadir',
        };
    }

    /**
     * Key `schools.settings` yang menyalakan skenario ini per sekolah.
     */
    public function settingKey(): string
    {
        return match ($this) {
            self::Terlambat => 'wa_alert_terlambat',
            self::Alpa => 'wa_alert_alpa',
        };
    }
}
