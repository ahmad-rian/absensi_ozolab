<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\ClassroomFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    /** @use HasFactory<ClassroomFactory> */
    use BelongsToSchool, HasFactory, HasUlids;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'name',
        'grade_level',
        'homeroom_teacher_id',
        'capacity',
    ];

    protected function casts(): array
    {
        return [
            'grade_level' => 'integer',
            'capacity' => 'integer',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homeroom_teacher_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function attendanceSchedules(): HasMany
    {
        return $this->hasMany(AttendanceSchedule::class);
    }
}
