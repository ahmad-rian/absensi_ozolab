<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\LibraryVisitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryVisit extends Model
{
    /** @use HasFactory<LibraryVisitFactory> */
    use BelongsToSchool, HasFactory, HasUlids;

    protected $fillable = [
        'school_id',
        'student_id',
        'visit_date',
        'entered_at',
        'exited_at',
        'device_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'entered_at' => 'datetime',
            'exited_at' => 'datetime',
        ];
    }

    /**
     * Kunjungan yang belum ditutup — siswa masih di dalam, atau lupa scan keluar.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeStillInside(Builder $query): Builder
    {
        return $query->whereNull('exited_at');
    }

    /**
     * Lama kunjungan dalam menit. Null selama belum discan keluar — sengaja bukan
     * nol, supaya laporan bisa membedakan "sebentar sekali" dari "belum keluar".
     */
    public function durationMinutes(): ?int
    {
        if (! $this->exited_at) {
            return null;
        }

        return (int) $this->entered_at->diffInMinutes($this->exited_at);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
