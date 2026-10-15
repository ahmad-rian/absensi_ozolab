<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\PenyelenggaraFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Tenant modul Absensi Multi-Kategori (biro haji, panitia, instansi).
 *
 * Sengaja terpisah dari School: tenancy sekolah tidak disentuh, dan akun
 * penyelenggara tidak pernah punya `school_id`.
 */
class Penyelenggara extends Model
{
    /** @use HasFactory<PenyelenggaraFactory> */
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'participant_quota',
        'wa_daily_quota',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'participant_quota' => 'integer',
            'wa_daily_quota' => 'integer',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $penyelenggara): void {
            if (! $penyelenggara->slug) {
                $penyelenggara->slug = static::uniqueSlug($penyelenggara->name);
            }
        });
    }

    /**
     * Slug unik; nama boleh sama, slug dibedakan dengan akhiran angka.
     */
    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'penyelenggara';
        $slug = $base;
        $n = 2;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'is_active', 'participant_quota', 'wa_daily_quota'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return ($this->settings ?? [])[$key] ?? $default;
    }

    public function setSetting(string $key, mixed $value): void
    {
        $this->settings = array_merge($this->settings ?? [], [$key => $value]);
    }

    /**
     * @return HasMany<User, $this>
     */
    /**
     * Admin Penyelenggara yang masih aktif. Setiap penyelenggara wajib punya
     * minimal satu — dijaga di Tim penyelenggara dan Pengguna general.
     */
    public function jumlahAdminAktif(): int
    {
        return User::where('penyelenggara_id', $this->id)
            ->where('is_active', true)
            ->role(UserRole::AdminPenyelenggara->value)
            ->count();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<PenyelenggaraInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(PenyelenggaraInvitation::class)->withoutGlobalScope('penyelenggara');
    }
}
