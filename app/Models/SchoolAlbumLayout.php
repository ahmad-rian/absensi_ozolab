<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class SchoolAlbumLayout extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id',
        'name',
        'paper_size',
        'orientation',
        'columns',
        'rows',
        'layout_config',
        'thumbnail_path',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'columns' => 'integer',
            'rows' => 'integer',
            'layout_config' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
