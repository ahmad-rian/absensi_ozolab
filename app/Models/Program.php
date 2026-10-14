<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPenyelenggara;
use Database\Factories\ProgramFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Satu periode berjalan dalam kategori (mis. "Haji 1448H").
 *
 * Setiap program punya satu layout kartu (`card_forms`) yang dibuat otomatis;
 * peserta program adalah baris `card_form_submissions` pada layout itu.
 */
class Program extends Model
{
    /** @use HasFactory<ProgramFactory> */
    use BelongsToPenyelenggara, HasFactory, HasUlids;

    protected $fillable = ['penyelenggara_id', 'kategori_id', 'name', 'starts_on', 'ends_on', 'timezone', 'card_form_id', 'archived_at', 'scanner_token'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'archived_at' => 'datetime'];
    }

    protected $hidden = ['scanner_token'];

    protected static function booted(): void
    {
        static::creating(function (self $program): void {
            $program->scanner_token = $program->scanner_token ?: Str::random(48);
        });

        static::created(function (self $program): void {
            if (! $program->card_form_id) {
                $program->buatLayoutKartu();
            }
        });
    }

    public function buatLayoutKartu(): CardForm
    {
        // Lintas konteks: hook ini juga jalan dari factory, console, dan salin program.
        $kategori = Kategori::acrossPenyelenggara()->findOrFail($this->kategori_id);

        $form = CardForm::create([
            'created_by' => auth()->id(),
            'name' => $this->name,
            'token' => Str::random(10),
            'card_dataset_id' => $kategori->card_dataset_id,
            'fields' => $kategori->visibleFields(),
            'orientation' => 'portrait',
            'layout_config' => ['orientation' => 'portrait', 'elements' => []],
            'is_active' => true,
            'penyelenggara_id' => $this->penyelenggara_id,
            'program_id' => $this->id,
        ]);

        $this->forceFill(['card_form_id' => $form->id])->saveQuietly();

        return $form;
    }

    /**
     * Salin struktur grup ke program baru — tanpa peserta dan kehadiran (FR-007).
     */
    public function salinKe(string $name, string $startsOn, string $endsOn): self
    {
        return DB::transaction(function () use ($name, $startsOn, $endsOn) {
            $baru = static::create([
                'penyelenggara_id' => $this->penyelenggara_id,
                'kategori_id' => $this->kategori_id,
                'name' => $name,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'timezone' => $this->timezone,
            ]);

            $peta = [];
            $grups = $this->grups()->orderByRaw('parent_id is not null')->orderBy('urutan')->get();
            $sisa = $grups;
            // Induk lebih dulu: ulangi sampai semua grup punya induk yang sudah tersalin.
            while ($sisa->isNotEmpty()) {
                $berikut = collect();
                foreach ($sisa as $grup) {
                    if ($grup->parent_id && ! isset($peta[$grup->parent_id])) {
                        $berikut->push($grup);

                        continue;
                    }
                    $peta[$grup->id] = Grup::create([
                        'penyelenggara_id' => $baru->penyelenggara_id,
                        'program_id' => $baru->id,
                        'parent_id' => $grup->parent_id ? $peta[$grup->parent_id] : null,
                        'name' => $grup->name,
                        'urutan' => $grup->urutan,
                    ])->id;
                }
                $sisa = $berikut;
            }

            return $baru;
        });
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * @return BelongsTo<Kategori, $this>
     */
    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class);
    }

    /**
     * @return BelongsTo<CardForm, $this>
     */
    public function cardForm(): BelongsTo
    {
        return $this->belongsTo(CardForm::class);
    }

    /**
     * @return HasMany<Grup, $this>
     */
    public function grups(): HasMany
    {
        return $this->hasMany(Grup::class);
    }

    /**
     * @return HasMany<CardFormSubmission, $this>
     */
    public function peserta(): HasMany
    {
        return $this->hasMany(CardFormSubmission::class, 'program_id');
    }
}
