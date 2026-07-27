<?php

namespace App\Support;

use App\Enums\AppModule;
use App\Enums\SchoolFeature;
use App\Models\School;

/**
 * Status nyala/mati tiap fitur untuk satu sekolah.
 *
 * Dibungkus jadi value object dengan alasan yang sama seperti PrayerSettings:
 * supaya key `schools.settings` tidak tersebar sebagai string mentah di
 * middleware, sidebar, controller, dan command terjadwal.
 */
final class SchoolFeatures
{
    /**
     * @param  array<string, bool>  $flags  selalu berisi SEMUA case, tidak pernah parsial
     */
    private function __construct(
        public readonly ?string $schoolId,
        private readonly array $flags,
    ) {}

    /**
     * Sengaja tanpa memo statis: hasilnya cuma 14 pembacaan array dari model
     * yang sudah dimuat, sedangkan cache ber-key school id akan basi begitu
     * `schools.settings` diperbarui di request yang sama (dan di seluruh test
     * yang mengubah setting lalu memeriksa efeknya).
     */
    public static function for(?School $school): self
    {
        return new self($school?->id, self::resolveAll($school));
    }

    public function enabled(SchoolFeature $feature): bool
    {
        return $this->flags[$feature->value] ?? $feature->defaultEnabled();
    }

    public function disabled(SchoolFeature $feature): bool
    {
        return ! $this->enabled($feature);
    }

    /**
     * Modul tanpa fitur pemilik (Dashboard, Pengaturan, grup Sistem) selalu
     * lolos — itulah jaring pengaman yang mencegah admin mengunci dirinya.
     */
    public function allowsModule(AppModule $module): bool
    {
        $feature = SchoolFeature::forModule($module);

        return $feature === null || $this->enabled($feature);
    }

    /**
     * Bentuk yang dikirim ke Inertia dan dipakai form Pengaturan. Selalu utuh,
     * jadi frontend boleh memakai perbandingan ketat `=== false`.
     *
     * @return array<string, bool>
     */
    public function toArray(): array
    {
        return $this->flags;
    }

    /**
     * @return array<string, bool>
     */
    private static function resolveAll(?School $school): array
    {
        $flags = [];

        foreach (SchoolFeature::cases() as $feature) {
            $flags[$feature->value] = self::resolveOne($school, $feature);
        }

        return $flags;
    }

    private static function resolveOne(?School $school, SchoolFeature $feature): bool
    {
        if (! $school) {
            return $feature->defaultEnabled();
        }

        // getSetting() memakai `??`, jadi `false` yang tersimpan tetap
        // dikembalikan apa adanya; hanya key yang belum pernah ditulis (atau
        // ditulis null) yang jatuh ke default. Itu persis yang kita mau:
        // "belum diatur" bukan berarti "dimatikan".
        $stored = $school->getSetting($feature->settingKey());

        if ($stored !== null) {
            return (bool) $stored;
        }

        return $feature->defaultEnabled();
    }
}
