<?php

namespace App\Support;

use App\Enums\Religion;
use App\Models\School;
use App\Models\Student;
use Carbon\Carbon;

/**
 * Pengaturan absen sholat dzuhur milik satu sekolah.
 *
 * Dibungkus jadi value object supaya key `schools.settings` tidak tersebar
 * sebagai string mentah di controller, recorder, dan halaman laporan.
 */
class PrayerSettings
{
    public function __construct(
        public readonly bool $enabled,
        public readonly string $start,
        public readonly string $end,
        public readonly bool $allReligions,
    ) {}

    public static function for(School $school): self
    {
        return new self(
            enabled: (bool) $school->getSetting('prayer_enabled', config('attendance.prayer.enabled')),
            start: self::normalizeTime($school->getSetting('prayer_start', config('attendance.prayer.start'))),
            end: self::normalizeTime($school->getSetting('prayer_end', config('attendance.prayer.end'))),
            allReligions: (bool) $school->getSetting('prayer_all_religions', config('attendance.prayer.all_religions')),
        );
    }

    /**
     * Apakah siswa ini wajib absen sholat.
     */
    public function covers(Student $student): bool
    {
        return $this->allReligions || $student->religion === Religion::Islam;
    }

    public function isWithinWindow(Carbon $time): bool
    {
        $at = SchoolTime::toLocal($time)->format('H:i:s');

        return $at >= $this->start && $at <= $this->end;
    }

    /**
     * Format ramah-pengguna untuk pesan scanner, mis. "11:00".
     */
    public function displayStart(): string
    {
        return substr($this->start, 0, 5);
    }

    public function displayEnd(): string
    {
        return substr($this->end, 0, 5);
    }

    /**
     * @return array{enabled: bool, start: string, end: string, all_religions: bool}
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'start' => $this->displayStart(),
            'end' => $this->displayEnd(),
            'all_religions' => $this->allReligions,
        ];
    }

    /**
     * Terima "11:00" maupun "11:00:00" dan selalu kembalikan "HH:MM:SS",
     * supaya perbandingan string dengan jam scan selalu setara.
     */
    private static function normalizeTime(mixed $value): string
    {
        return Carbon::createFromTimeString((string) $value)->format('H:i:s');
    }
}
