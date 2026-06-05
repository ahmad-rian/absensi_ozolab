<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolWaConfig extends Model
{
    use BelongsToSchool, HasUlids;

    protected $fillable = [
        'school_id',
        'fonnte_token',
        'display_phone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fontte_token' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
