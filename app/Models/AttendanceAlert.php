<?php

namespace App\Models;

use App\Enums\AttendanceAlertKind;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\AttendanceAlertFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu kabar buruk absensi yang sudah (atau akan) dikirim ke orang tua.
 *
 * Satu baris per siswa per tanggal per jenis. `notified_at` distempel saat job
 * DIANTREKAN, bukan saat berhasil kirim: sapuan berjalan tiap jam, dan lebih
 * baik kurang-kirim daripada mengirim kabar yang sama enam kali. Kegagalannya
 * tetap terlihat sebagai NotificationLog berstatus FAILED di inbox admin.
 *
 * @property AttendanceAlertKind $kind
 */
class AttendanceAlert extends Model
{
    /** @use HasFactory<AttendanceAlertFactory> */
    use BelongsToSchool, HasFactory, HasUlids;

    protected $fillable = [
        'school_id',
        'student_id',
        'attendance_id',
        'alert_date',
        'kind',
        'notified_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => AttendanceAlertKind::class,
            'alert_date' => 'date',
            'notified_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }
}
