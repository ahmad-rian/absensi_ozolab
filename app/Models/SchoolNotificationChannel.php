<?php

namespace App\Models;

use App\Enums\SchoolChannelType;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolNotificationChannel extends Model
{
    use BelongsToSchool, HasUlids;

    protected $fillable = [
        'school_id',
        'channel',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'channel' => SchoolChannelType::class,
            'is_active' => 'boolean',
            'settings' => 'encrypted:array',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Ambil nilai kredensial dari kolom settings.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return ($this->settings ?? [])[$key] ?? $default;
    }
}
