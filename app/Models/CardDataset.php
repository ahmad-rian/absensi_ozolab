<?php

namespace App\Models;

use App\Models\Concerns\CardTenancy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Format Data": a reusable set of dynamic fields (like a Google Form schema),
 * decoupled from the card layout. One dataset can be used by many layouts.
 */
class CardDataset extends Model
{
    use CardTenancy, HasUlids;

    protected $fillable = [
        'created_by',
        'name',
        'fields',
        'penyelenggara_id',
        'kategori_id',
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
