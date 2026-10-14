<?php

namespace App\Enums;

/**
 * Saklar fitur per penyelenggara — status langganan tenant, bukan otorisasi.
 *
 * Pola sama dengan SchoolFeature, tetapi disimpan di `penyelenggaras.settings`
 * dan sumbunya terpisah supaya fitur sekolah dan penyelenggara tidak pernah
 * berbagi kunci.
 */
enum PenyelenggaraFeature: string
{
    case NotifikasiWali = 'notifikasi_wali';

    public function settingKey(): string
    {
        return 'feature_'.$this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::NotifikasiWali => 'Notifikasi wali',
        };
    }

    public function defaultEnabled(): bool
    {
        return match ($this) {
            self::NotifikasiWali => false,
        };
    }
}
