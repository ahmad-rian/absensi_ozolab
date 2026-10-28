<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu scan kartu Haji. Rekap harian / per sesi tetap di `card_attendances`
 * dan `kehadirans`; baris ini hanya jejak tiap scan untuk riwayat.
 *
 * Sengaja tanpa global scope tenant, sama seperti CardAttendance: tabel ini
 * hanya dibaca lewat layout / program / sesi yang SUDAH di-resolve dari link
 * scan atau dari model ber-tenant, tidak pernah dengan query bebas.
 */
class CardScan extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    /** Scan ulang peserta yang sama di bawah jeda ini diabaikan (scanner dobel). */
    public const JEDA_DETIK = 60;

    public const JENIS_MASUK = 'masuk';

    public const JENIS_PULANG = 'pulang';

    public const JENIS_DATANG = 'datang';

    /** Scan di antara masuk dan pulang: dicatat, rekap tidak berubah. */
    public const JENIS_SCAN = 'scan';

    protected $fillable = ['card_form_submission_id', 'card_form_id', 'program_id', 'sesi_id', 'jenis', 'scanned_at'];

    protected function casts(): array
    {
        return ['scanned_at' => 'datetime'];
    }

    public function peserta(): BelongsTo
    {
        return $this->belongsTo(CardFormSubmission::class, 'card_form_submission_id');
    }

    /**
     * Ada scan peserta ini dalam jeda terakhir? Satu query ber-indeks
     * (card_form_submission_id, scanned_at).
     *
     * @param  string  $sekarang  jam dinding Y-m-d H:i:s di zona peserta
     */
    public static function baruSaja(string $pesertaId, string $sekarang): bool
    {
        $batas = date('Y-m-d H:i:s', strtotime($sekarang) - self::JEDA_DETIK);

        return self::query()
            ->where('card_form_submission_id', $pesertaId)
            ->where('scanned_at', '>', $batas)
            ->where('scanned_at', '<=', $sekarang)
            ->exists();
    }
}
