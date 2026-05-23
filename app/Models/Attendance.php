<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use BelongsToSchool, HasFactory, HasUlids;

    protected $fillable = [
        'school_id',
        'student_id',
        'attendance_date',
        'type',
        'status',
        'recorded_at',
        'recorded_by',
        'device_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'type' => AttendanceType::class,
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

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }
}
