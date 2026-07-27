<?php

namespace App\Support;

use App\Enums\PrayerType;
use App\Enums\Religion;
use App\Models\School;
use App\Models\Student;
use Carbon\Carbon;

/**
 * Pengaturan satu jenis sholat (Dhuha / Dzuhur) milik satu sekolah.
 *
 * Dibungkus jadi value object supaya key `schools.settings` tidak tersebar
 * sebagai string mentah di controller, recorder, dan halaman laporan.
 *
 * `$type` diletakkan sebagai argumen TERAKHIR dengan default Dzuhur supaya
 * seluruh pemanggil lama tetap jalan tanpa diubah.
 */
class PrayerSettings
{
    public function __construct(
        public readonly bool $enabled,
        public readonly string $start,
        public readonly string $end,
        public readonly bool $allReligions,
        public readonly PrayerType $type = PrayerType::Dzuhur,
    ) {}

    public static function for(School $school, PrayerType $type = PrayerType::Dzuhur): self
    {
        $fallback = $type->defaultWindow();

        return new self(
            enabled: (bool) $school->getSetting(
                $type->settingKey('enabled'),
                config($type->configPrefix().'.enabled', false),
            ),
            start: self::normalizeTime($school->getSetting(
                $type->settingKey('start'),
                config($type->configPrefix().'.start', $fallback['start']),
            )),
            end: self::normalizeTime($school->getSetting(
                $type->settingKey('end'),
                config($type->configPrefix().'.end', $fallback['end']),
            )),
            // Kepesertaan lintas agama itu kebijakan sekolah terhadap SISWA,
            // bukan properti waktu sholat — jadi satu key lama
            // `prayer_all_religions` sengaja dipakai bersama kedua jenis.
            allReligions: (bool) $school->getSetting(
                'prayer_all_religions',
                config('attendance.prayer.all_religions'),
            ),
            type: $type,
        );
    }

    /**
     * Apakah siswa ini wajib absen sholat.
     *
     * `prayer_opt_in` per siswa menang atas aturan sekolah; `null` berarti
     * ikut aturan sekolah. Sengaja tidak dibedakan per jenis sholat: siswa
     * non-Muslim yang di-opt-in sekolah hampir pasti ikut keduanya, dan
     * memecahnya berarti sembilan kombinasi state untuk kebutuhan yang belum
     * pernah diminta.
     */
    public function covers(Student $student): bool
    {
        if ($student->prayer_opt_in !== null) {
            return $student->prayer_opt_in;
        }

        return $this->allReligions || $student->religion === Religion::Islam;
    }

    public function isWithinWindow(Carbon $time): bool
    {
        $at = SchoolTime::toLocal($time)->format('H:i:s');

        return $at >= $this->start && $at <= $this->end;
    }

    /**
     * Dua jendela dianggap bertabrakan kalau ada satu detik pun yang masuk
     * keduanya. Batasnya inklusif di kedua ujung (lihat isWithinWindow),
     * jadi "selesai 09:00" + "mulai 09:00" TETAP dihitung tumpang tindih.
     */
    public function overlapsWith(self $other): bool
    {
        return $this->start <= $other->end && $other->start <= $this->end;
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
     * "07:30 – 09:00"
     */
    public function windowLabel(): string
    {
        return $this->displayStart().' – '.$this->displayEnd();
    }

    /**
     * Key `enabled/start/end/all_religions` dipertahankan persis supaya
     * halaman scan dan test yang sudah ada tidak perlu diubah.
     *
     * @return array{enabled: bool, start: string, end: string, all_religions: bool, type: string, type_label: string, type_short: string, window: string}
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'start' => $this->displayStart(),
            'end' => $this->displayEnd(),
            'all_religions' => $this->allReligions,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'type_short' => $this->type->shortLabel(),
            'window' => $this->windowLabel(),
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
