<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\Religion;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use BelongsToSchool, HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'school_id',
        'parent_profile_id',
        'classroom_id',
        'nis',
        'no_absen',
        'nisn',
        'full_name',
        'gender',
        'religion',
        'prayer_opt_in',
        'birth_place',
        'birth_date',
        'address',
        'parent_name',
        'parent_phone',
        'photo_path',
        'qr_token',
        'qr_issued_at',
        'qr_rotated_at',
        'rfid_uid',
        'rfid_registered_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'religion' => Religion::class,
            'prayer_opt_in' => 'boolean',
            'birth_date' => 'date',
            'qr_issued_at' => 'datetime',
            'qr_rotated_at' => 'datetime',
            'rfid_registered_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Nama siswa disimpan huruf besar.
     *
     * Kapitalisasinya masuk campur dari impor tiap sekolah, dan nama yang sama
     * bisa datang lewat delapan jalur tulis berbeda (form admin, pendaftaran
     * publik, pendaftaran orang tua, impor, seeder, factory). Semuanya lewat
     * mass-assignment atau `fill()`, jadi mutator di sini menangkap semuanya —
     * termasuk jalur baru yang belum ada.
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => $this->toUpperName($value));
    }

    /**
     * Nama orang tua pada data pendaftaran, diseragamkan bersama nama siswa.
     *
     * Ini BUKAN nama akun login orang tua (`users.name`), yang sengaja
     * dibiarkan apa adanya: itu identitas akun mereka sendiri dan muncul di
     * halaman profil, email, serta notifikasi.
     */
    protected function parentName(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => $this->toUpperName($value));
    }

    /**
     * `mb_strtoupper`, bukan `strtoupper`, supaya huruf beraksen tidak rusak.
     * Null diteruskan apa adanya — kolomnya nullable, dan string kosong bukan
     * hal yang sama dengan "belum diisi".
     */
    private function toUpperName(?string $value): ?string
    {
        return $value === null ? null : mb_strtoupper(trim($value));
    }

    public function parentProfile(): BelongsTo
    {
        return $this->belongsTo(ParentProfile::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function classHistories(): HasMany
    {
        return $this->hasMany(StudentClassHistory::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function prayerAttendances(): HasMany
    {
        return $this->hasMany(PrayerAttendance::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }
}
