<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPenyelenggara;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Kehadiran satu peserta pada satu sesi (spec 004). Status memakai
 * AttendanceStatus sekolah (HADIR/TERLAMBAT/ALPA/IZIN/SAKIT).
 */
class Kehadiran extends Model
{
    use BelongsToPenyelenggara, HasUlids, LogsActivity;

    public const SUMBER_SCAN = 'scan';

    public const SUMBER_MANUAL = 'manual';

    public const SUMBER_OTOMATIS = 'otomatis';

    protected $fillable = [
        'penyelenggara_id', 'program_id', 'sesi_id', 'card_form_submission_id', 'status',
        'datang_at', 'pulang_at', 'sumber', 'catatan', 'dicatat_oleh',
    ];

    protected function casts(): array
    {
        return ['datang_at' => 'datetime', 'pulang_at' => 'datetime'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'catatan', 'sumber', 'datang_at', 'pulang_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return BelongsTo<Sesi, $this>
     */
    public function sesi(): BelongsTo
    {
        return $this->belongsTo(Sesi::class);
    }

    /**
     * @return BelongsTo<CardFormSubmission, $this>
     */
    public function peserta(): BelongsTo
    {
        return $this->belongsTo(CardFormSubmission::class, 'card_form_submission_id');
    }
}
