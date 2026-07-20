<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Format Data": a reusable set of dynamic fields (like a Google Form schema),
 * decoupled from the card layout. One dataset can be used by many layouts.
 */
class CardDataset extends Model
{
    use HasUlids;

    protected $fillable = [
        'created_by',
        'name',
        'fields',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
        ];
    }

    public function forms(): HasMany
    {
        return $this->hasMany(CardForm::class);
    }
}
