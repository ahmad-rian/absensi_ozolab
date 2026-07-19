<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolCardLayout extends Model
{
    use BelongsToSchool, HasUlids;

    protected $fillable = [
        'school_id',
        'name',
        'type',
        'layout_config',
        'thumbnail_path',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'layout_config' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function generationLogs(): HasMany
    {
        return $this->hasMany(CardGenerationLog::class);
    }

    /**
     * The canonical list of draggable card elements with their defaults (mm-based).
     * Shared source of truth for the React editor and the Blade generator.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function defaultElements(): array
    {
        return [
            'field_nama' => ['type' => 'field', 'label' => 'NAMA', 'source' => 'full_name', 'x' => 3.0, 'y' => 16.0, 'width' => 55.0, 'labelWidth' => 20.0, 'fontSize' => 2.0, 'enabled' => true],
            'field_alamat' => ['type' => 'field', 'label' => 'ALAMAT', 'source' => 'address', 'x' => 3.0, 'y' => 19.2, 'width' => 55.0, 'labelWidth' => 20.0, 'fontSize' => 2.0, 'enabled' => true],
            'field_ttl' => ['type' => 'field', 'label' => 'TTL', 'source' => 'ttl', 'x' => 3.0, 'y' => 22.4, 'width' => 55.0, 'labelWidth' => 20.0, 'fontSize' => 2.0, 'enabled' => true],
            'field_agama' => ['type' => 'field', 'label' => 'AGAMA', 'source' => 'religion', 'x' => 3.0, 'y' => 25.6, 'width' => 55.0, 'labelWidth' => 20.0, 'fontSize' => 2.0, 'enabled' => true],
            'field_noinduk' => ['type' => 'field', 'label' => 'NO.INDUK', 'source' => 'nis', 'x' => 3.0, 'y' => 28.8, 'width' => 55.0, 'labelWidth' => 20.0, 'fontSize' => 2.0, 'enabled' => true],
            'field_nisn' => ['type' => 'field', 'label' => 'NISN', 'source' => 'nisn', 'x' => 3.0, 'y' => 32.0, 'width' => 55.0, 'labelWidth' => 20.0, 'fontSize' => 2.0, 'enabled' => false],
            'field_kelas' => ['type' => 'field', 'label' => 'KELAS', 'source' => 'classroom', 'x' => 3.0, 'y' => 35.2, 'width' => 55.0, 'labelWidth' => 20.0, 'fontSize' => 2.0, 'enabled' => false],
            'field_jk' => ['type' => 'field', 'label' => 'JENIS KELAMIN', 'source' => 'gender', 'x' => 3.0, 'y' => 38.4, 'width' => 55.0, 'labelWidth' => 20.0, 'fontSize' => 2.0, 'enabled' => false],
            'field_telp' => ['type' => 'field', 'label' => 'NO. HP', 'source' => 'parent_phone', 'x' => 3.0, 'y' => 41.6, 'width' => 55.0, 'labelWidth' => 20.0, 'fontSize' => 2.0, 'enabled' => false],
            'photo' => ['type' => 'photo', 'x' => 2.5, 'y' => 30.0, 'w' => 16.0, 'h' => 21.0, 'enabled' => true],
            'qr' => ['type' => 'qr', 'x' => 22.0, 'y' => 33.0, 'size' => 15.0, 'enabled' => true],
        ];
    }

    /**
     * Normalize the stored layout_config into the element-based schema consumed
     * identically by the editor preview and the card generator. Legacy configs
     * (flat frame_* keys) are migrated to `elements` on the fly for backward compat.
     *
     * @return array<string, mixed>
     */
    public function normalizedConfig(): array
    {
        $config = $this->layout_config ?? [];
        $defaults = static::defaultElements();

        if (! empty($config['elements']) && is_array($config['elements'])) {
            $elements = [];
            foreach ($defaults as $key => $default) {
                $elements[$key] = array_merge($default, $config['elements'][$key] ?? []);
            }
        } else {
            $elements = $this->deriveElementsFromLegacy($defaults, $config);
        }

        return array_merge($config, [
            'orientation' => in_array($config['orientation'] ?? null, ['landscape', 'portrait'], true)
                ? $config['orientation']
                : 'landscape',
            'frame_id' => $config['frame_id'] ?? null,
            'elements' => $elements,
        ]);
    }

    /**
     * Build the element map from legacy flat config keys (frame_body_*, frame_photo_*, frame_qr_*).
     *
     * @param  array<string, array<string, mixed>>  $defaults
     * @param  array<string, mixed>  $config
     * @return array<string, array<string, mixed>>
     */
    private function deriveElementsFromLegacy(array $defaults, array $config): array
    {
        $bodyTop = (float) ($config['frame_body_top'] ?? 16);
        $bodyLeft = (float) ($config['frame_body_left'] ?? 3);
        $bodyFont = (float) ($config['frame_body_font'] ?? 2.0);
        $step = $bodyFont * 1.6;

        $legacyFieldOrder = ['field_nama', 'field_alamat', 'field_ttl', 'field_agama', 'field_noinduk'];
        $elements = $defaults;
        $i = 0;

        foreach ($legacyFieldOrder as $key) {
            $elements[$key] = array_merge($elements[$key], [
                'x' => $bodyLeft,
                'y' => round($bodyTop + ($step * $i), 2),
                'fontSize' => $bodyFont,
                'enabled' => true,
            ]);
            $i++;
        }

        $elements['photo'] = array_merge($elements['photo'], [
            'x' => (float) ($config['frame_photo_left'] ?? 2.5),
            'y' => (float) ($config['frame_photo_top'] ?? 30),
            'w' => (float) ($config['frame_photo_w'] ?? 16),
            'h' => (float) ($config['frame_photo_h'] ?? 21),
            'enabled' => true,
        ]);

        $elements['qr'] = array_merge($elements['qr'], [
            'x' => (float) ($config['frame_qr_left'] ?? 22),
            'y' => (float) ($config['frame_qr_top'] ?? 33),
            'size' => (float) ($config['frame_qr_size'] ?? 15),
            'enabled' => (bool) ($config['show_qr'] ?? true),
        ]);

        return $elements;
    }
}
