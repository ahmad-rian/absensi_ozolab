<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPenyelenggara;
use App\Support\KategoriTemplates;
use Database\Factories\KategoriFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Jenis kegiatan milik penyelenggara (Haji/Umroh, Pelatihan, …).
 *
 * Skema kolom peserta disimpan di `card_datasets.fields` supaya editor kartu
 * dan validator peserta Kartu Bebas bisa dipakai ulang apa adanya.
 */
class Kategori extends Model
{
    /** @use HasFactory<KategoriFactory> */
    use BelongsToPenyelenggara, HasFactory, HasUlids;

    public const FITUR = ['terlambat', 'pulang', 'izin_sakit', 'notifikasi_wali', 'portal_wali', 'rfid'];

    protected $fillable = ['penyelenggara_id', 'name', 'slug', 'template', 'istilah_peserta', 'istilah_grup', 'features', 'card_dataset_id', 'archived_at'];

    protected function casts(): array
    {
        return ['features' => 'array', 'archived_at' => 'datetime'];
    }

    /**
     * Buat kategori dari salah satu template bawaan, lengkap dengan skemanya.
     */
    public static function dariTemplate(Penyelenggara $penyelenggara, string $templateKey, ?string $name, ?User $by): self
    {
        $template = KategoriTemplates::find($templateKey);
        abort_unless($template, 422, 'Template tidak dikenal.');

        return DB::transaction(function () use ($penyelenggara, $templateKey, $template, $name, $by) {
            $kategori = static::create([
                'penyelenggara_id' => $penyelenggara->id,
                'name' => $name ?: $template['name'],
                'slug' => static::slugUnik($penyelenggara, $name ?: $template['name']),
                'template' => $templateKey,
                'istilah_peserta' => $template['istilah_peserta'],
                'istilah_grup' => $template['istilah_grup'],
                'features' => $template['features'],
            ]);

            $dataset = CardDataset::create([
                'created_by' => $by?->id,
                'name' => $kategori->name,
                'fields' => KategoriTemplates::withCore($template['fields']),
                'penyelenggara_id' => $penyelenggara->id,
                'kategori_id' => $kategori->id,
            ]);

            $kategori->forceFill(['card_dataset_id' => $dataset->id])->save();

            return $kategori;
        });
    }

    public static function slugUnik(Penyelenggara $penyelenggara, string $name): string
    {
        $base = Str::slug($name) ?: 'kategori';
        $slug = $base;
        $n = 2;

        while (static::acrossPenyelenggara()->where('penyelenggara_id', $penyelenggara->id)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    /**
     * Semua kolom, termasuk yang disembunyikan (data lama tetap ada).
     *
     * @return array<int, array<string, mixed>>
     */
    public function fields(): array
    {
        return array_values($this->dataset?->fields ?? []);
    }

    /**
     * Kolom yang tampil di form & impor.
     *
     * @return array<int, array<string, mixed>>
     */
    public function visibleFields(): array
    {
        return array_values(array_filter($this->fields(), fn (array $f) => empty($f['hidden'])));
    }

    /**
     * Ganti skema kolom. Kolom yang hilang dari input disembunyikan, bukan
     * dibuang, supaya data peserta lama tetap bisa dimunculkan lagi (FR-004).
     *
     * @param  array<int, array<string, mixed>>  $fields
     */
    public function simpanSkema(array $fields): void
    {
        $baru = collect($fields)->keyBy('key');
        $lama = collect($this->fields())->keyBy('key');

        $hasil = $baru->values()->all();
        foreach ($lama as $key => $field) {
            if (! $baru->has($key)) {
                $hasil[] = [...$field, 'hidden' => true];
            }
        }

        $hasil = KategoriTemplates::withCore($hasil);

        DB::transaction(function () use ($hasil) {
            $this->dataset->update(['fields' => $hasil, 'name' => $this->name]);

            $visible = array_values(array_filter($hasil, fn (array $f) => empty($f['hidden'])));
            CardForm::whereIn('id', $this->programs()->whereNotNull('card_form_id')->pluck('card_form_id'))
                ->update(['fields' => json_encode($visible)]);
        });
    }

    public function fitur(string $key): bool
    {
        return (bool) (($this->features ?? [])[$key] ?? false);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * @return BelongsTo<CardDataset, $this>
     */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(CardDataset::class, 'card_dataset_id');
    }

    /**
     * @return HasMany<Program, $this>
     */
    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }
}
