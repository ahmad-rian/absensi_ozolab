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
            if (! $model->school_id && ($schoolId = static::currentSchoolId())) {
                $model->school_id = $schoolId;
            }
        });

        // Kunci semua query ke sekolah user yang sedang login. Ini yang menutup
        // akses lintas sekolah lewat route-model binding (mis. /admin/siswa/{id}
        // milik sekolah lain), tanpa bergantung pada tiap controller ingat
        // memanggil ->forSchool().
        //
        // Konteks tanpa user login (console, queue, webhook, halaman publik)
        // tidak ter-scope; jalur-jalur itu memfilter school_id sendiri.
        if (! static::schoolScopeApplies()) {
            return;
        }

        static::addGlobalScope('school', function (Builder $query): void {
            if ($schoolId = static::currentSchoolId()) {
                $query->where($query->getModel()->getTable().'.school_id', $schoolId);
            }
        });
    }

    /**
     * Model yang di-opt-out dari global scope — dipakai User, yang batas
     * sekolahnya ditegakkan di controller karena relasi auth/notifikasi ikut
     * memuat User dari konteks lintas sekolah.
     */
    public static function schoolScopeApplies(): bool
    {
        return true;
    }

    /**
     * Sekolah aktif milik user yang sedang login.
     *
     * Memakai `hasUser()` dan bukan `check()`: `check()` akan me-resolve user
     * dari session, dan karena User sendiri memakai trait ini, itu memicu
     * rekursi tak terbatas saat guard pertama kali mengambil user.
     */
    protected static function currentSchoolId(): ?string
    {
        $guard = auth();

        return $guard->hasUser() ? $guard->user()?->school_id : null;
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Scope untuk memfilter data berdasarkan sekolah.
     *
     * Sejak global scope `school` aktif, pemanggilan eksplisit ini hanya perlu
     * saat menargetkan sekolah lain atau saat berjalan tanpa user login.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForSchool(Builder $query, ?string $schoolId = null): Builder
    {
        $schoolId = $schoolId ?? static::currentSchoolId();

        if ($schoolId) {
            return $query->withoutGlobalScope('school')
                ->where($this->getTable().'.school_id', $schoolId);
        }

        // Jika tidak ada school_id, return empty result untuk mencegah data leak
        return $query->withoutGlobalScope('school')->whereRaw('1 = 0');
    }

    /**
     * Lepas batas sekolah — hanya untuk operasi lintas sekolah yang disengaja.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAcrossSchools(Builder $query): Builder
    {
        return $query->withoutGlobalScope('school');
    }
}
