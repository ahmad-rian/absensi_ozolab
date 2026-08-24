<?php

namespace App\Support;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Models\NotificationLog;
use App\Models\School;

/**
 * Batas jumlah pesan WhatsApp yang boleh dikirim satu sekolah per hari.
 *
 * Nomor WhatsApp yang belum terverifikasi (belum bercentang biru) diblokir
 * penyedia kalau mengirim terlalu banyak pesan dalam sehari, dan blokirnya
 * mengenai seluruh sekolah — bukan hanya pesan yang kelebihan. Batas ini
 * menahan diri lebih dulu supaya nomornya tetap hidup.
 *
 * Penghitungnya `notification_logs`, bukan penghitung terpisah: satu sumber
 * kebenaran yang sama dengan yang dilihat admin di Inbox Notifikasi.
 */
class WhatsAppQuota
{
    public const DEFAULT_LIMIT = 50;

    /**
     * Batas harian sekolah, atau null kalau nomornya sudah terverifikasi.
     */
    public static function limitFor(School $school): ?int
    {
        if ((bool) ($school->getSetting('wa_verified') ?? false)) {
            return null;
        }

        $limit = $school->getSetting('wa_daily_limit');

        // Nilai tak masuk akal (0, negatif, teks) dikembalikan ke bawaan, bukan
        // dipakai apa adanya — batas 0 akan membungkam sekolah tanpa satu pun
        // pesan galat yang bisa dilihat admin.
        return is_numeric($limit) && (int) $limit > 0
            ? (int) $limit
            : self::DEFAULT_LIMIT;
    }

    /**
     * Jumlah pesan WhatsApp yang sudah benar-benar terkirim hari ini.
     */
    public static function sentToday(string $schoolId): int
    {
        // Batas harinya dihitung di zona sekolah lalu dikonversi ke UTC.
        // `sent_at` ditulis dengan `now()` yang tunduk pada config('app.timezone')
        // = UTC, sementara "hari ini" bagi sekolah adalah jam dinding lokal.
        // `whereDate()` membandingkan keduanya apa adanya, jadi pesan pukul
        // 23.30 WIB terhitung sebagai hari berikutnya dan kuota seolah reset
        // tujuh jam terlalu cepat.
        $start = SchoolTime::today()->utc();
        $end = SchoolTime::today()->endOfDay()->utc();

        return NotificationLog::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('channel', NotificationChannel::Whatsapp->value)
            ->where('status', NotificationStatus::Sent->value)
            ->whereBetween('sent_at', [$start, $end])
            ->count();
    }

    /**
     * Masih boleh mengirim satu pesan lagi hari ini?
     */
    public static function allows(School $school): bool
    {
        $limit = self::limitFor($school);

        return $limit === null || self::sentToday($school->id) < $limit;
    }

    /**
     * Sisa jatah hari ini, atau null kalau tanpa batas. Untuk ditampilkan di
     * halaman Pengaturan.
     */
    public static function remaining(School $school): ?int
    {
        $limit = self::limitFor($school);

        return $limit === null
            ? null
            : max(0, $limit - self::sentToday($school->id));
    }
}
