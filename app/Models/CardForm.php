<?php

namespace App\Models;

use App\Models\Concerns\CardTenancy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CardForm extends Model
{
    use CardTenancy, HasUlids;

    protected $hidden = ['scanner_token'];

    protected $fillable = [
        'created_by',
        'name',
        'card_dataset_id',
        'token',
        'fields',
        'orientation',
        'frame_id',
        'layout_config',
        'is_active',
        'form_publik',
        'penyelenggara_id',
        'program_id',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'accept_legacy_qr' => 'boolean',
            'layout_config' => 'array',
            'is_active' => 'boolean',
            'form_publik' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CardForm $form): void {
            $form->scanner_token = $form->scanner_token ?: Str::random(48);
        });
    }

    /**
     * Link form pendaftaran publik — dibangun dari rute, jadi ikut domain dan
     * token saat ini.
     */
    public function publicUrl(): string
    {
        return route('public.card-forms.show', $this->token);
    }

    /**
     * Boleh diisi publik? Form Haji/Kartu Bebas (tanpa program) cukup aktif —
     * link-nya sudah beredar. Form program wajib dinyalakan eksplisit dan
     * programnya masih berjalan di penyelenggara yang aktif.
     */
    public function terbukaUntukPublik(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->program_id === null) {
            return true;
        }

        if ($this->form_publik !== true) {
            return false;
        }

        $program = Program::acrossPenyelenggara()->find($this->program_id);

        return $program !== null
            && ! $program->isArchived()
            && (bool) Penyelenggara::whereKey($this->penyelenggara_id)->value('is_active');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(CardFormSubmission::class);
    }

    public function cardDataset(): BelongsTo
    {
        return $this->belongsTo(CardDataset::class);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function inputFields(): array
    {
        $fields = array_values($this->fields ?? []);
        if (collect($fields)->contains('type', 'photo')) {
            return $fields;
        }

        foreach ($this->normalizedConfig()['elements'] as $element) {
            if (($element['type'] ?? '') === 'photo' && ! empty($element['enabled'])) {
                $fields[] = ['key' => $element['source'], 'label' => 'Foto', 'type' => 'photo', 'required' => false];
                break;
            }
        }

        return $fields;
    }

    /**
     * Normalize the stored layout_config for the editor / generator (mirrors
     * SchoolCardLayout: orientation + frame + elements). Elements' `source` keys
     * are the form's dynamic field keys instead of fixed student fields.
     *
     * @return array<string, mixed>
     */
    public function normalizedConfig(): array
    {
        $config = $this->layout_config ?? [];

        return array_merge($config, [
            'orientation' => in_array($config['orientation'] ?? $this->orientation, ['landscape', 'portrait'], true)
                ? ($config['orientation'] ?? $this->orientation)
                : 'landscape',
            'frame_id' => $config['frame_id'] ?? $this->frame_id,
            'elements' => $config['elements'] ?? [],
        ]);
    }
}
