<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kelas yang ditempati seorang siswa pada satu tahun ajaran.
 *
 * `is_current` redundan terhadap academic_years.is_active, tapi dipertahankan
 * supaya daftar siswa kelas berjalan bisa diambil lewat satu index tanpa join
 * ke tabel tahun ajaran.
 */
class StudentClassHistory extends Model
{
    use BelongsToSchool, HasUlids;

    protected $fillable = [
        'school_id',
        'student_id',
        'classroom_id',
        'academic_year_id',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
