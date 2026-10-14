<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPenyelenggara;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Aturan sesi: berulang mingguan, rentang tanggal, atau sekali (spec 003).
 * Jam adalah wall clock zona waktu program.
 */
class SesiPola extends Model
{
    use BelongsToPenyelenggara, HasUlids;

    public const MINGGUAN = 'mingguan';

    public const RENTANG = 'rentang';

    public const SEKALI = 'sekali';

    public const JENDELA = ['datang_mulai', 'datang_selesai', 'batas_terlambat', 'pulang_mulai', 'pulang_selesai'];

    protected $fillable = [
        'penyelenggara_id', 'program_id', 'grup_id', 'name', 'pengulangan', 'hari', 'tanggal_mulai',
        'tanggal_selesai', 'tanggal', 'datang_mulai', 'datang_selesai', 'batas_terlambat', 'pulang_mulai', 'pulang_selesai',
    ];

    protected function casts(): array
    {
        // Tanggal disimpan sebagai string Y-m-d (lihat Sesi) — tanpa cast `date`.
        return ['hari' => 'array'];
    }

    /**
     * Tanggal (Y-m-d) tempat pola ini berlaku, dipotong ke rentang program.
     *
     * @return array<int, string>
     */
    public function tanggalBerlaku(Program $program): array
    {
        $mulai = CarbonImmutable::parse($program->starts_on);
        $selesai = CarbonImmutable::parse($program->ends_on);

        if ($this->pengulangan === self::SEKALI) {
            $t = CarbonImmutable::parse($this->tanggal);

            return $t->betweenIncluded($mulai, $selesai) ? [$t->toDateString()] : [];
        }

        if ($this->pengulangan === self::RENTANG) {
            $mulai = $this->tanggal_mulai ? CarbonImmutable::parse($this->tanggal_mulai)->max($mulai) : $mulai;
            $selesai = $this->tanggal_selesai ? CarbonImmutable::parse($this->tanggal_selesai)->min($selesai) : $selesai;
        }

        if ($mulai->gt($selesai)) {
            return [];
        }

        $hari = array_map('intval', $this->hari ?? []);
        $hasil = [];
        foreach (CarbonPeriod::create($mulai, $selesai) as $tanggal) {
            if (($this->pengulangan === self::RENTANG && $hari === []) || in_array($tanggal->dayOfWeekIso, $hari, true)) {
                $hasil[] = $tanggal->toDateString();
            }
        }

        return $hasil;
    }

    /**
     * @return array<string, string|null>
     */
    public function jendela(): array
    {
        return $this->only(self::JENDELA);
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
     * @return HasMany<Sesi, $this>
     */
    public function sesis(): HasMany
    {
        return $this->hasMany(Sesi::class);
    }
}
