<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardFormSubmission extends Model
{
    use HasUlids;

    protected $fillable = [
        'card_form_id',
        'data',
        'photo_path',
        'file_path',
        'drive_file_id',
        'drive_url',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function cardForm(): BelongsTo
    {
        return $this->belongsTo(CardForm::class);
    }
}
