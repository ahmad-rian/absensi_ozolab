<?php

namespace App\Models;

use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'favicon_path',
        'address',
        'city',
        'phone',
        'email',
        'website',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function attendanceSchedules(): HasMany
    {
        return $this->hasMany(AttendanceSchedule::class);
    }

    public function parentProfiles(): HasMany
    {
        return $this->hasMany(ParentProfile::class);
    }

    public function driveConfig(): HasOne
    {
        return $this->hasOne(SchoolDriveConfig::class);
    }

    public function frames(): HasMany
    {
        return $this->hasMany(SchoolFrame::class);
    }

    public function cardLayouts(): HasMany
    {
        return $this->hasMany(SchoolCardLayout::class);
    }

    public function albumLayouts(): HasMany
    {
        return $this->hasMany(SchoolAlbumLayout::class);
    }

    public function cardGenerationLogs(): HasMany
    {
        return $this->hasMany(CardGenerationLog::class);
    }

    /**
     * Ambil pengaturan sekolah berdasarkan key dari kolom settings JSON.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        $settings = $this->settings ?? [];

        return $settings[$key] ?? $default;
    }

    /**
     * Simpan pengaturan sekolah ke kolom settings JSON.
     */
    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->settings = $settings;
        $this->save();
    }
}
