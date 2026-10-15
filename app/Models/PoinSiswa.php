<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Satu catatan poin pelanggaran. `tanggal` sengaja tanpa cast `date` — dibaca
 * sebagai string Y-m-d supaya perbandingan sama di MySQL dan SQLite.
 *
 * Nilai poin disalin saat dicatat: mengubah setup sekolah tidak menulis ulang
 * riwayat.
 */
class PoinSiswa extends Model
{
    use BelongsToSchool, HasUlids, LogsActivity;

    public const TERLAMBAT = 'TERLAMBAT';

    public const ALPA = 'ALPA';

    public const MANUAL = 'MANUAL';

    public const KUNCI_OTOMATIS = 'auto';

    protected $fillable = [
        'school_id', 'student_id', 'tanggal', 'sumber', 'kunci_otomatis',
        'kategori', 'poin', 'alasan', 'dicatat_oleh',
    ];

    protected function casts(): array
    {
        return ['poin' => 'integer'];
    }

    public function getTanggalAttribute(?string $value): ?string
    {
        return $value ? substr($value, 0, 10) : null;
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    public function isManual(): bool
    {
        return $this->sumber === self::MANUAL;
    }

    public function label(): string
    {
        return match ($this->sumber) {
            self::TERLAMBAT => 'Terlambat',
            self::ALPA => 'Tidak hadir',
            default => $this->kategori ?? 'Pelanggaran',
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['sumber', 'kategori', 'poin', 'alasan', 'tanggal'])->dontLogEmptyChanges();
    }
}
