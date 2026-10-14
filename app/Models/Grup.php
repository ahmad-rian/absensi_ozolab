<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPenyelenggara;
use Database\Factories\GrupFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pengelompokan peserta bertingkat dalam satu program (kloter → rombongan → regu).
 */
class Grup extends Model
{
    /** @use HasFactory<GrupFactory> */
    use BelongsToPenyelenggara, HasFactory, HasUlids;

    protected $fillable = ['penyelenggara_id', 'program_id', 'parent_id', 'name', 'urutan'];

    /**
     * Id grup ini beserta semua turunannya. Pohon per program kecil (ratusan
     * simpul), jadi cukup dihitung di PHP dari satu query.
     *
     * @param  array<int, string>  $grupIds
     * @return array<int, string>
     */
    public static function denganTurunan(string $programId, array $grupIds): array
    {
        $anak = static::where('program_id', $programId)->get(['id', 'parent_id'])->groupBy('parent_id');
        $hasil = [];
        $antri = $grupIds;

        while ($antri) {
            $id = array_shift($antri);
            if (isset($hasil[$id])) {
                continue;
            }
            $hasil[$id] = true;
            foreach ($anak->get($id, collect()) as $child) {
                $antri[] = $child->id;
            }
        }

        return array_keys($hasil);
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
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Grup, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsToMany<CardFormSubmission, $this>
     */
    public function peserta(): BelongsToMany
    {
        return $this->belongsToMany(CardFormSubmission::class, 'grup_peserta')->withTimestamps();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function pendamping(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'grup_pendamping')->withTimestamps();
    }
}
