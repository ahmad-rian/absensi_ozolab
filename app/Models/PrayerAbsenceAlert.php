<?php

namespace App\Models;

use App\Enums\PrayerType;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu rentetan "tidak sholat" yang sudah menembus ambang sekolah.
 *
 * Baris tetap terbuka (`resolved_at` null) sampai siswa ikut sholat lagi;
 * selama terbuka dan sudah `notified_at`, tidak ada notifikasi kedua.
 */
class PrayerAbsenceAlert extends Model
{
    use BelongsToSchool, HasUlids;

    protected $fillable = [
        'school_id',
        'student_id',
        'prayer_type',
        'streak_start_date',
        'streak_last_date',
        'streak_length',
        'combined_types',
        'notified_at',
        'delivered_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'prayer_type' => PrayerType::class,
            'streak_start_date' => 'date',
            'streak_last_date' => 'date',
            'streak_length' => 'integer',
            'combined_types' => 'array',
            'notified_at' => 'datetime',
            'delivered_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }
}
