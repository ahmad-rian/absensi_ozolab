<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Pengajuan izin/sakit siswa. `tanggal_mulai`/`tanggal_selesai` sengaja tanpa
 * cast `date` — dibaca sebagai string Y-m-d supaya perbandingan sama di MySQL
 * dan SQLite (lihat catatan jebakan tanggal di repo).
 */
class PengajuanIzin extends Model
{
    use BelongsToSchool, HasUlids, LogsActivity;

    public const MENUNGGU = 'menunggu';

    public const DISETUJUI = 'disetujui';

    public const DITOLAK = 'ditolak';

    public const SUMBER_PORTAL = 'portal';

    public const SUMBER_PUBLIK = 'publik';

    /** Rentang izin terpanjang yang boleh diajukan sekaligus, dalam hari. */
    public const MAKS_HARI = 14;

    protected $fillable = [
        'school_id', 'student_id', 'parent_profile_id', 'sumber', 'jenis',
        'tanggal_mulai', 'tanggal_selesai', 'alasan', 'bukti_path',
        'pengirim_nama', 'pengirim_telepon', 'status', 'catatan_review',
        'reviewed_by', 'reviewed_at', 'hari_dicatat',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'hari_dicatat' => 'integer'];
    }

    public function getTanggalMulaiAttribute(?string $value): ?string
    {
        return $value ? substr($value, 0, 10) : null;
    }

    public function getTanggalSelesaiAttribute(?string $value): ?string
    {
        return $value ? substr($value, 0, 10) : null;
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isMenunggu(): bool
    {
        return $this->status === self::MENUNGGU;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'catatan_review'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
