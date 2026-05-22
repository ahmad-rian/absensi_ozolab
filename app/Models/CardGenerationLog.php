<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardGenerationLog extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id',
        'student_id',
        'school_card_layout_id',
        'type',
        'status',
        'file_path',
        'drive_file_id',
        'drive_url',
        'generated_by',
        'error_message',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function cardLayout(): BelongsTo
    {
        return $this->belongsTo(SchoolCardLayout::class, 'school_card_layout_id');
    }
}
