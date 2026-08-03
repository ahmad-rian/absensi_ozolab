<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoSheetBatch extends Model
{
    use BelongsToSchool, HasUlids;

    protected $fillable = [
        'school_id',
        'template',
        'status',
        'items',
        'total_slots',
        'pages',
        'file_path',
        'error_message',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'total_slots' => 'integer',
            'pages' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
