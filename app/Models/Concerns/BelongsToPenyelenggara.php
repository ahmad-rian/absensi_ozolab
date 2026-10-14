<?php

namespace App\Models\Concerns;

use App\Models\Penyelenggara;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Batas tenant penyelenggara — FAIL-CLOSED.
 *
 * Berbeda dari BelongsToSchool yang tidak memfilter apa pun saat konteksnya
 * kosong: di sini konteks kosong berarti nol baris. Konteks dibaca dari
 * container (`currentPenyelenggara`, diisi SetCurrentPenyelenggara), bukan
 * langsung dari user, supaya super admin bisa berpindah penyelenggara.
 *
 * Console, queue, dan rute publik yang memang lintas tenant memakai
 * `acrossPenyelenggara()` lalu memfilter `penyelenggara_id` sendiri.
 */
trait BelongsToPenyelenggara
{
    public static function bootBelongsToPenyelenggara(): void
    {
        static::creating(function ($model): void {
            if (! $model->penyelenggara_id && ($id = static::currentPenyelenggaraId())) {
                $model->penyelenggara_id = $id;
            }
        });

        static::addGlobalScope('penyelenggara', function (Builder $query): void {
            $id = static::currentPenyelenggaraId();
            $column = $query->getModel()->getTable().'.penyelenggara_id';

            $id ? $query->where($column, $id) : $query->whereRaw('1 = 0');
        });
    }

    public static function currentPenyelenggaraId(): ?string
    {
        return app()->bound('currentPenyelenggara') ? app('currentPenyelenggara')?->id : null;
    }

    /**
     * @return BelongsTo<Penyelenggara, $this>
     */
    public function penyelenggara(): BelongsTo
    {
        return $this->belongsTo(Penyelenggara::class);
    }

    /**
     * Lepas batas penyelenggara — hanya untuk operasi lintas tenant yang disengaja.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAcrossPenyelenggara(Builder $query): Builder
    {
        return $query->withoutGlobalScope('penyelenggara');
    }
}
