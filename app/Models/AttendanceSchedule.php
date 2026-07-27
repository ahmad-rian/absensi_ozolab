<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Support\SchoolTime;
use Carbon\Carbon;
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
            'check_in_start' => 'string',
            'check_in_end' => 'string',
            'late_threshold' => 'string',
            'check_out_start' => 'string',
            'check_out_end' => 'string',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * Kolom TIME dinormalisasi ke "HH:MM:SS" supaya perbandingan string antar
     * jadwal selalu setara, apa pun format yang dikembalikan driver database.
     */
    public function timeOf(string $column): string
    {
        return Carbon::createFromTimeString((string) $this->{$column})->format('H:i:s');
    }

    /**
     * Format ramah-pengguna untuk pesan scanner, mis. "06:00".
     */
    public function displayTimeOf(string $column): string
    {
        return substr($this->timeOf($column), 0, 5);
    }

    /**
     * Gabungkan kolom jam dengan sebuah tanggal, dalam zona waktu sekolah.
     */
    public function momentOn(string $column, Carbon $date): Carbon
    {
        return $date->copy()
            ->setTimezone(SchoolTime::timezone())
            ->setTimeFromTimeString($this->timeOf($column));
    }
}
