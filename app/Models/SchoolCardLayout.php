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
}
