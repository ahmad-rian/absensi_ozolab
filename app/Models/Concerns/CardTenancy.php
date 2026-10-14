<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Batas tenant untuk tabel kartu yang dipakai bersama Kartu Bebas (lama) dan
 * modul penyelenggara (spec 002).
 *
 * Tiga keadaan:
 * 1. Di area penyelenggara (`currentPenyelenggara` terikat) → hanya baris
 *    penyelenggara itu.
 * 2. Di area Kartu Bebas (penanda `kartuBebasLegacy`) → hanya baris lama
 *    (`penyelenggara_id` NULL), jadi super admin tidak pernah melihat data
 *    penyelenggara di sana.
 * 2b. Di area penyelenggara tanpa konteks (penanda `areaPenyelenggara`) →
 *    nol baris.
 * 3. Selain itu (job generate, console, rute token publik) → tanpa filter,
 *    persis perilaku sebelum modul ini ada. Akun penyelenggara tidak pernah
 *    mencapai keadaan ini karena terkunci di areanya sendiri.
 *
 * Bukan fail-closed penuh — dibenarkan di specs/002/plan.md (Complexity
 * Tracking): fail-closed mematikan job kartu dan scan haji yang berjalan.
 */
trait CardTenancy
{
    public static function bootCardTenancy(): void
    {
        static::creating(function ($model): void {
            if (! $model->penyelenggara_id && app()->bound('currentPenyelenggara')) {
                $model->penyelenggara_id = app('currentPenyelenggara')->id;
            }
        });

        static::addGlobalScope('penyelenggara', function (Builder $query): void {
            $column = $query->getModel()->getTable().'.penyelenggara_id';

            if (app()->bound('currentPenyelenggara')) {
                $query->where($column, app('currentPenyelenggara')->id);
            } elseif (app()->bound('areaPenyelenggara')) {
                // Area penyelenggara tanpa konteks (super admin belum memilih):
                // fail-closed, bukan keadaan 3.
                $query->whereRaw('1 = 0');
            } elseif (app()->bound('kartuBebasLegacy')) {
                $query->whereNull($column);
            }
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAcrossPenyelenggara(Builder $query): Builder
    {
        return $query->withoutGlobalScope('penyelenggara');
    }
}
