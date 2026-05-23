<?php

namespace App\Models\Concerns;

use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        // Otomatis set school_id saat membuat record baru jika belum diisi
        static::creating(function ($model) {
            if (! $model->school_id && auth()->check()) {
                $model->school_id = auth()->user()->school_id;
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Scope untuk memfilter data berdasarkan sekolah.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForSchool(Builder $query, ?int $schoolId = null): Builder
    {
        $schoolId = $schoolId ?? auth()->user()?->school_id;

        if ($schoolId) {
            return $query->where($this->getTable().'.school_id', $schoolId);
        }

        // Jika tidak ada school_id, return empty result untuk mencegah data leak
        return $query->whereRaw('1 = 0');
    }
}
