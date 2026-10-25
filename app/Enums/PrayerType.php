<?php

namespace App\Enums;

use App\Models\School;

/**
 * Jenis sholat berjamaah yang diabsen.
 *
 * Urutan case sengaja kronologis: PrayerSchedule mencocokkan jam scan dengan
 * menelusuri jenis dari yang paling pagi, jadi hasilnya tetap deterministik
 * bahkan kalau ada data lama yang jendelanya terlanjur tumpang tindih.
 */
enum PrayerType: string
{
    case Dhuha = 'DHUHA';
    case Dzuhur = 'DZUHUR';

    /**
     * Panjang maksimum nama tampilan yang boleh diisi sekolah.
     */
    public const NAMA_MAKS = 30;

    /**
     * "Sholat Dhuha", atau nama yang diatur sekolah.
     *
     * Nama sekolah yang sudah diawali "Sholat" dipakai apa adanya supaya
     * tidak jadi "Sholat Sholat ...".
     *
     * Tanpa argumen, sekolah diambil dari konteks request. Pemanggil yang
     * berjalan di luar request (console, antrean, rute gerbang tanpa
     * session) WAJIB mengirim sekolahnya sendiri.
     */
    public function label(?School $school = null): string
    {
        $nama = $this->shortLabel($school);

        return preg_match('/^(sholat|shalat|salat|solat)\b/i', $nama) ? $nama : 'Sholat '.$nama;
    }

    public function shortLabel(?School $school = null): string
    {
        $school ??= app()->bound('currentSchool') ? app('currentSchool') : null;
        $nama = $school instanceof School ? trim((string) $school->getSetting($this->settingKey('nama'), '')) : '';

        return $nama !== '' ? $nama : $this->namaBawaan();
    }

    public function namaBawaan(): string
    {
        return match ($this) {
            self::Dhuha => 'Dhuha',
            self::Dzuhur => 'Dzuhur',
        };
    }

    public function slug(): string
    {
        return strtolower($this->value);
    }

    /**
     * Prefix key di kolom `schools.settings`.
     *
     * Dzuhur sengaja memakai prefix lama `prayer` — BUKAN `prayer_dzuhur` —
     * supaya enam sekolah produksi yang sudah menyimpan prayer_enabled /
     * prayer_start / prayer_end tidak perlu disentuh sama sekali. Key lama
     * otomatis jadi key Dzuhur.
     */
    public function settingPrefix(): string
    {
        return match ($this) {
            self::Dhuha => 'prayer_dhuha',
            self::Dzuhur => 'prayer',
        };
    }

    public function settingKey(string $suffix): string
    {
        return $this->settingPrefix().'_'.$suffix;
    }

    /**
     * Prefix di config/attendance.php, dengan alasan yang sama seperti
     * settingPrefix(): blok `prayer` lama tetap jadi sumber default Dzuhur.
     */
    public function configPrefix(): string
    {
        return match ($this) {
            self::Dhuha => 'attendance.prayer_dhuha',
            self::Dzuhur => 'attendance.prayer',
        };
    }

    /**
     * Jendela pabrikan, dipakai kalau config maupun setting sekolah kosong.
     *
     * @return array{start: string, end: string}
     */
    public function defaultWindow(): array
    {
        return match ($this) {
            self::Dhuha => ['start' => '07:30', 'end' => '09:00'],
            self::Dzuhur => ['start' => '11:00', 'end' => '13:00'],
        };
    }

    /**
     * Device id default untuk scan publik. Dzuhur menahan nilai lama
     * 'PRAYER-SCAN' supaya riwayat lama dan baru tetap satu label di laporan.
     */
    public function deviceId(): string
    {
        return match ($this) {
            self::Dhuha => 'PRAYER-SCAN-DHUHA',
            self::Dzuhur => 'PRAYER-SCAN',
        };
    }

    public static function fromSlug(?string $slug): ?self
    {
        return $slug ? self::tryFrom(strtoupper($slug)) : null;
    }

    /**
     * @return array<int, self>
     */
    public static function chronological(): array
    {
        return [self::Dhuha, self::Dzuhur];
    }
}
