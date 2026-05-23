<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\AttendanceScheduleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSchedule extends Model
{
    /** @use HasFactory<AttendanceScheduleFactory> */
    use BelongsToSchool, HasFactory, HasUlids;

    protected $fillable = [
        'school_id',
        'classroom_id',
        'day_of_week',
        'check_in_start',
        'check_in_end',
        'late_threshold',
        'check_out_start',
        'check_out_end',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
