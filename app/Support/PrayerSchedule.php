<?php

namespace App\Support;

use App\Enums\PrayerType;
use App\Models\School;
use App\Models\Student;
use Carbon\Carbon;

/**
 * Kumpulan jendela sholat satu sekolah.
 *
 * Ini yang membuat satu URL scan bisa melayani dua jenis sholat: jam scan
 * dicocokkan ke jendela, bukan link yang dipisah. Petugas mushola cukup
 * memegang satu tautan dan tidak bisa salah mode.
 */
class PrayerSchedule
{
    /**
     * @param  array<string, PrayerSettings>  $settings  di-key oleh PrayerType::value
     */
    private function __construct(
        private readonly array $settings,
    ) {}

    public static function for(School $school): self
    {
        $settings = [];

        foreach (PrayerType::chronological() as $type) {
            $settings[$type->value] = PrayerSettings::for($school, $type);
        }

        return new self($settings);
    }

    public function get(PrayerType $type): PrayerSettings
    {
        return $this->settings[$type->value];
    }

    /**
     * @return array<int, PrayerSettings>
     */
    public function all(): array
    {
        return array_values($this->settings);
    }

    /**
     * @return array<int, PrayerSettings>
     */
    public function enabled(): array
    {
        return array_values(array_filter($this->all(), fn (PrayerSettings $s) => $s->enabled));
    }

    public function anyEnabled(): bool
    {
        return $this->enabled() !== [];
    }

    /**
     * Jenis sholat yang jendelanya memuat jam ini.
     *
     * Dijamin tidak ambigu karena Pengaturan menolak jendela yang tumpang
     * tindih; kalau data lama terlanjur bertabrakan, yang paling pagi menang
     * supaya hasilnya tetap deterministik (urutan PrayerType::chronological).
     */
    public function resolveFor(Carbon $time): ?PrayerSettings
    {
        foreach ($this->enabled() as $settings) {
            if ($settings->isWithinWindow($time)) {
                return $settings;
            }
        }

        return null;
    }

    /**
     * Kalimat untuk pesan galat scanner.
     *
     * Satu jenis : "11:00 s/d 13:00"  — bentuk lama, sengaja dijaga.
     * Dua jenis  : "07:30 s/d 09:00 (Dhuha) atau 11:00 s/d 13:00 (Dzuhur)"
     */
    public function windowsSentence(): string
    {
        $enabled = $this->enabled();

        if (count($enabled) === 1) {
            return $enabled[0]->displayStart().' s/d '.$enabled[0]->displayEnd();
        }

        return implode(' atau ', array_map(
            fn (PrayerSettings $s) => $s->displayStart().' s/d '.$s->displayEnd().' ('.$s->type->shortLabel().')',
            $enabled,
        ));
    }

    /**
     * Rentang jendela untuk ditampilkan, mis. "11:00 – 13:00" saat satu jenis
     * aktif dan "07:30 – 09:00 · 11:00 – 13:00" saat keduanya aktif.
     */
    public function windowsLabel(): string
    {
        return implode(' · ', array_map(
            fn (PrayerSettings $s) => $s->windowLabel(),
            $this->enabled(),
        ));
    }

    /**
     * Pasangan jendela aktif yang bertabrakan, untuk validasi Pengaturan.
     *
     * @return array<int, array{0: PrayerSettings, 1: PrayerSettings}>
     */
    public function overlapping(): array
    {
        $enabled = $this->enabled();
        $pairs = [];
        $count = count($enabled);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($enabled[$i]->overlapsWith($enabled[$j])) {
                    $pairs[] = [$enabled[$i], $enabled[$j]];
                }
            }
        }

        return $pairs;
    }

    /**
     * Aturan kepesertaan identik untuk semua jenis, jadi cukup cek satu.
     */
    public function covers(Student $student): bool
    {
        return $this->get(PrayerType::Dzuhur)->covers($student);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'any_enabled' => $this->anyEnabled(),
            'windows' => array_map(fn (PrayerSettings $s) => $s->toArray(), $this->all()),
            'enabled_windows' => array_map(fn (PrayerSettings $s) => $s->toArray(), $this->enabled()),
            'windows_sentence' => $this->anyEnabled() ? $this->windowsSentence() : null,
            'windows_label' => $this->anyEnabled() ? $this->windowsLabel() : null,
        ];
    }
}
