<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\PrayerAttendanceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrayerAttendance extends Model
{
    /** @use HasFactory<PrayerAttendanceFactory> */
    use BelongsToSchool, HasFactory, HasUlids;

    protected $fillable = [
        'school_id',
        'student_id',
        'prayer_date',
        'status',
        'recorded_at',
        'recorded_by',
        'device_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'prayer_date' => 'date',
            'status' => AttendanceStatus::class,
            'recorded_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
