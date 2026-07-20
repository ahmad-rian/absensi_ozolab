<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardForm extends Model
{
    use HasUlids;

    protected $fillable = [
        'created_by',
        'name',
        'card_dataset_id',
        'token',
        'fields',
        'orientation',
        'frame_id',
        'layout_config',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'layout_config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(CardFormSubmission::class);
    }

    public function cardDataset(): BelongsTo
    {
        return $this->belongsTo(CardDataset::class);
    }

    /**
     * Normalize the stored layout_config for the editor / generator (mirrors
     * SchoolCardLayout: orientation + frame + elements). Elements' `source` keys
     * are the form's dynamic field keys instead of fixed student fields.
     *
     * @return array<string, mixed>
     */
    public function normalizedConfig(): array
    {
        $config = $this->layout_config ?? [];

        return array_merge($config, [
            'orientation' => in_array($config['orientation'] ?? $this->orientation, ['landscape', 'portrait'], true)
                ? ($config['orientation'] ?? $this->orientation)
                : 'landscape',
            'frame_id' => $config['frame_id'] ?? $this->frame_id,
            'elements' => $config['elements'] ?? [],
        ]);
    }
}
