<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPenyelenggara;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu kejadian sesi pada satu tanggal, dengan SALINAN jendela waktunya —
 * mengubah pola tidak mengubah penilaian kehadiran yang sudah lewat.
 */
class Sesi extends Model
{
    use BelongsToPenyelenggara, HasUlids;

    protected $fillable = [
        'penyelenggara_id', 'program_id', 'sesi_pola_id', 'grup_id', 'name', 'tanggal',
        'datang_mulai', 'datang_selesai', 'batas_terlambat', 'pulang_mulai', 'pulang_selesai',
    ];

    /**
     * `tanggal` sengaja tanpa cast `date`: SQLite menyimpan komponen jam pada
     * cast itu sehingga `whereIn('tanggal', ['2026-06-01'])` tidak pernah cocok.
     * Disimpan dan dibaca sebagai string Y-m-d di kedua database.
     */
    protected function setTanggalAttribute(mixed $value): void
    {
        $this->attributes['tanggal'] = substr((string) $value, 0, 10);
    }

    public function getTanggalAttribute(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 10);
    }

    /** Akhir sesi: akhir jendela pulang bila ada, selain itu akhir jendela datang. */
    public function selesai(): string
    {
        return $this->pulang_selesai ?? $this->datang_selesai;
    }

    public function punyaKehadiran(): bool
    {
        return $this->kehadirans()->exists();
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * @return BelongsTo<Grup, $this>
     */
    public function grup(): BelongsTo
    {
        return $this->belongsTo(Grup::class);
    }

    /**
     * @return HasMany<Kehadiran, $this>
     */
    public function kehadirans(): HasMany
    {
        return $this->hasMany(Kehadiran::class);
    }
}
