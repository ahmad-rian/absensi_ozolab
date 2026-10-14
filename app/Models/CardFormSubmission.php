<?php

namespace App\Models;

use App\Models\Concerns\CardTenancy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardFormSubmission extends Model
{
    use CardTenancy, HasUlids;

    protected $fillable = [
        'card_form_id',
        'data',
        'photo_path',
        'original_photo_path',
        'manual_crop',
        'generation_token',
        'generation_error',
        'file_path',
        'drive_file_id',
        'drive_url',
        'status',
        'penyelenggara_id',
        'program_id',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'manual_crop' => 'array',
        ];
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(CardAttendance::class);
    }

    public function cardForm(): BelongsTo
    {
        return $this->belongsTo(CardForm::class);
    }

    /**
     * Peserta modul penyelenggara mewarisi program dari layoutnya. Baris Kartu
     * Bebas lama: layoutnya tanpa program, jadi tetap NULL.
     */
    protected static function booted(): void
    {
        static::creating(function (self $submission): void {
            if ($submission->program_id || ! $submission->card_form_id) {
                return;
            }

            $form = CardForm::acrossPenyelenggara()->whereKey($submission->card_form_id)->first(['program_id', 'penyelenggara_id']);
            $submission->program_id = $form?->program_id;
            $submission->penyelenggara_id ??= $form?->penyelenggara_id;
        });
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Kehadiran per sesi (modul penyelenggara, spec 004). Peserta Kartu Bebas
     * lama tidak punya baris di sini; absensinya tetap di card_attendances.
     *
     * @return HasMany<Kehadiran, $this>
     */
    public function kehadirans(): HasMany
    {
        return $this->hasMany(Kehadiran::class, 'card_form_submission_id');
    }

    /**
     * @return BelongsToMany<Grup, $this>
     */
    public function grups(): BelongsToMany
    {
        return $this->belongsToMany(Grup::class, 'grup_peserta')->withTimestamps();
    }
}
