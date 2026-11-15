<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\ClassroomFactory;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Hanya kelas milik tahun ajaran aktif sekolahnya.
     *
     * Nama kelas ("7A") dibuat ulang tiap tahun, jadi daftar pilihan yang
     * memuat semua tahun berisi nama kembar yang tidak terbedakan - dan siswa
     * baru bisa terdaftar ke kelas tahun lalu.
     *
     * Dua kelonggaran yang disengaja:
     * - Sekolah yang belum punya tahun ajaran aktif tetap melihat semua
     *   kelasnya, supaya dropdown tidak mendadak kosong.
     * - `$termasuk` menyertakan kelas tertentu apa pun tahunnya: kelas yang
     *   sedang ditempati siswa, atau yang sedang dipakai sebagai filter.
     *
     * Subquery-nya berkorelasi per baris, jadi aman dipakai lintas sekolah
     * (form publik, konsol) tanpa mengandalkan global scope `school`.
     *
     * @param  Builder<static>  $query
     * @param  string|array<int, string|null>|null  $termasuk
     * @return Builder<static>
     */
    public function scopeTahunAktif(Builder $query, string|array|null $termasuk = null): Builder
    {
        $termasuk = array_values(array_filter((array) $termasuk, fn ($id) => is_string($id) && $id !== ''));

        return $query->where(function (Builder $q) use ($termasuk): void {
            $q->whereIn('classrooms.academic_year_id', fn ($aktif) => $aktif
                ->select('id')->from('academic_years')->where('is_active', true))
                ->orWhereNotExists(fn ($aktif) => $aktif
                    ->selectRaw('1')->from('academic_years as tahun_aktif')
                    ->whereColumn('tahun_aktif.school_id', 'classrooms.school_id')
                    ->where('tahun_aktif.is_active', true));

            if ($termasuk !== []) {
                $q->orWhereIn('classrooms.id', $termasuk);
            }
        });
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
