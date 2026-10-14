<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\BelongsToPenyelenggara;
use Database\Factories\PenyelenggaraInvitationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Undangan email ke akun penyelenggara.
 *
 * Token mentah hanya ada di email; database menyimpan SHA-256-nya, jadi bocornya
 * tabel tidak memberi siapa pun tautan undangan yang masih berlaku.
 */
class PenyelenggaraInvitation extends Model
{
    /** @use HasFactory<PenyelenggaraInvitationFactory> */
    use BelongsToPenyelenggara, HasFactory, HasUlids, LogsActivity;

    public const BERLAKU_HARI = 7;

    public const STATUS_MENUNGGU = 'menunggu';

    public const STATUS_DIPAKAI = 'dipakai';

    public const STATUS_KEDALUWARSA = 'kedaluwarsa';

    public const STATUS_DIBATALKAN = 'dibatalkan';

    protected $fillable = ['penyelenggara_id', 'email', 'role', 'token_hash', 'invited_by', 'expires_at', 'accepted_at', 'revoked_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Buat undangan baru dan kembalikan token mentahnya (hanya untuk email).
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(Penyelenggara $penyelenggara, string $email, UserRole $role, ?User $by): array
    {
        $token = Str::random(48);

        $invitation = static::create([
            'penyelenggara_id' => $penyelenggara->id,
            'email' => Str::lower(trim($email)),
            'role' => $role->value,
            'token_hash' => hash('sha256', $token),
            'invited_by' => $by?->id,
            'expires_at' => now()->addDays(self::BERLAKU_HARI),
        ]);

        return [$invitation, $token];
    }

    /**
     * Cari undangan dari token mentah, lintas penyelenggara (pengunjung belum login).
     */
    public static function findByToken(string $token): ?self
    {
        return static::acrossPenyelenggara()->where('token_hash', hash('sha256', $token))->first();
    }

    public function status(): string
    {
        return match (true) {
            $this->accepted_at !== null => self::STATUS_DIPAKAI,
            $this->revoked_at !== null => self::STATUS_DIBATALKAN,
            $this->expires_at->isPast() => self::STATUS_KEDALUWARSA,
            default => self::STATUS_MENUNGGU,
        };
    }

    public function isUsable(): bool
    {
        return $this->status() === self::STATUS_MENUNGGU;
    }

    public function roleEnum(): UserRole
    {
        return UserRole::from($this->role);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['email', 'role', 'accepted_at', 'revoked_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
