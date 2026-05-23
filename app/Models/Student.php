<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\Religion;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use BelongsToSchool, HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'school_id',
        'parent_profile_id',
        'classroom_id',
        'nis',
        'no_absen',
        'nisn',
        'full_name',
        'gender',
        'religion',
        'birth_place',
        'birth_date',
        'address',
        'parent_name',
        'parent_phone',
        'photo_path',
        'qr_token',
        'qr_issued_at',
        'qr_rotated_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'religion' => Religion::class,
            'birth_date' => 'date',
            'qr_issued_at' => 'datetime',
            'qr_rotated_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function parentProfile(): BelongsTo
    {
        return $this->belongsTo(ParentProfile::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }
}
