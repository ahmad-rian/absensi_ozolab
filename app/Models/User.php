<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AppModule;
use App\Enums\ModulGeneral;
use App\Enums\UserRole;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'phone', 'avatar_path', 'is_active', 'school_id', 'penyelenggara_id', 'must_change_password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use BelongsToSchool, HasFactory, HasRoles, HasUlids, LogsActivity, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * User tidak ikut global scope sekolah: guard, relasi orang tua, dan
     * notifikasi memuat User dari konteks lintas sekolah. Pembatasan sekolah
     * untuk User ditegakkan eksplisit di UserManagementController.
     */
    public static function schoolScopeApplies(): bool
    {
        return false;
    }

    /**
     * Akun penyelenggara tidak pernah sekaligus akun sekolah. Dijaga di model
     * supaya jalur pembuatan user mana pun tidak bisa melahirkan akun ganda
     * tenant — `school_id` yang ikut terisi akan membuat global scope sekolah
     * menganggapnya warga sekolah itu.
     */
    protected static function booted(): void
    {
        $lepasSekolah = function (self $user): void {
            if ($user->penyelenggara_id) {
                $user->school_id = null;
            }
        };

        // `creating` ikut dipasang karena hook `creating` BelongsToSchool
        // (terdaftar lebih dulu) mengisi school_id dari user yang login,
        // SETELAH `saving` berjalan.
        static::saving($lepasSekolah);
        static::creating($lepasSekolah);
    }

    /**
     * @return BelongsTo<Penyelenggara, $this>
     */
    public function penyelenggara(): BelongsTo
    {
        return $this->belongsTo(Penyelenggara::class);
    }

    public function isPenyelenggaraUser(): bool
    {
        return $this->penyelenggara_id !== null;
    }

    /**
     * Akun staf Tyas di workspace General — tanpa sekolah dan tanpa
     * penyelenggara; modulnya permission langsung per akun.
     */
    public function isStafGeneral(): bool
    {
        return $this->hasRole(UserRole::StafGeneral->value) && ! $this->isSuperAdmin();
    }

    /**
     * Boleh memilih dan membuka penyelenggara mana pun (seperti super admin)?
     */
    public function bolehLintasPenyelenggara(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->isStafGeneral()
            && $this->is_active
            && $this->hasAnyPermission([
                ModulGeneral::PenyelenggaraMasuk->permission(),
                ModulGeneral::Kategori->permission(),
                ModulGeneral::Program->permission(),
            ]);
    }

    /**
     * Izin `penyelenggara-*` yang diwakilkan modul General: Masuk Area
     * Penyelenggara = admin penuh; Kategori / Program hanya beranda plus
     * menunya sendiri — tidak tim, kelola peserta, atau kartu.
     */
    public function aksesPenyelenggaraLewatGeneral(string $ability): bool
    {
        if (! str_starts_with($ability, 'penyelenggara-') || ! $this->isStafGeneral() || ! $this->is_active) {
            return false;
        }

        if ($this->hasPermissionTo(ModulGeneral::PenyelenggaraMasuk->permission())) {
            return true;
        }

        $kategori = $this->hasPermissionTo(ModulGeneral::Kategori->permission());
        $program = $this->hasPermissionTo(ModulGeneral::Program->permission());

        return match ($ability) {
            AppModule::PenyelenggaraBeranda->permission() => $kategori || $program,
            AppModule::PenyelenggaraKategori->permission() => $kategori,
            AppModule::PenyelenggaraProgram->permission() => $program,
            default => false,
        };
    }

    public function homeRoute(): string
    {
        if ($this->isPenyelenggaraUser()) {
            return 'penyelenggara.dashboard';
        }

        if ($this->isStafGeneral()) {
            return 'general.dashboard';
        }

        return $this->hasRole(UserRole::OrangTua) ? 'orangtua.index' : 'dashboard';
    }

    public function requiresPasswordChange(): bool
    {
        $isParentOnly = $this->hasRole(UserRole::OrangTua)
            && ! $this->hasAnyRole(['ADMIN', 'GURU', 'SUPER_ADMIN']);

        return $this->must_change_password && ! $isParentOnly;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(UserRole::SuperAdmin->value);
    }

    public function parentProfile(): HasOne
    {
        return $this->hasOne(ParentProfile::class);
    }

    public function homeroomClassrooms(): HasMany
    {
        return $this->hasMany(Classroom::class, 'homeroom_teacher_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'is_active', 'penyelenggara_id'])
            ->logOnlyDirty();
    }
}
