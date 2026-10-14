<?php

namespace App\Support;

use App\Enums\PenyelenggaraFeature;
use App\Models\Penyelenggara;

/**
 * Status nyala/mati tiap fitur untuk satu penyelenggara (pola SchoolFeatures).
 */
final class PenyelenggaraFeatures
{
    /**
     * @param  array<string, bool>  $flags  selalu berisi SEMUA case
     */
    private function __construct(private readonly array $flags) {}

    public static function for(?Penyelenggara $penyelenggara): self
    {
        $flags = [];

        foreach (PenyelenggaraFeature::cases() as $feature) {
            $stored = $penyelenggara?->getSetting($feature->settingKey());
            $flags[$feature->value] = $stored === null ? $feature->defaultEnabled() : (bool) $stored;
        }

        return new self($flags);
    }

    public function enabled(PenyelenggaraFeature $feature): bool
    {
        return $this->flags[$feature->value];
    }

    /**
     * @return array<string, bool>
     */
    public function toArray(): array
    {
        return $this->flags;
    }
}
