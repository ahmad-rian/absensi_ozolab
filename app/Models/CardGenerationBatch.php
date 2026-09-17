<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu kali tekan tombol "Generate" di layar generate massal.
 *
 * Sengaja TIDAK memakai `BelongsToSchool`. Batch dibuat super admin yang
 * memilih sekolahnya dari daftar, dan sekolah itu belum tentu sekolah aktif di
 * sesinya — global scope `school` akan menyembunyikan batch yang baru saja ia
 * buat sendiri. Penjagaannya ada di middleware `super-admin` pada ketiga rute
 * `generate-kartu`, pola yang sama dengan `School` dan `Role`.
 */
class CardGenerationBatch extends Model
{
    use HasUlids;

    protected $fillable = [
        'school_id',
        'classroom_id',
        'created_by',
        'total',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CardGenerationLog::class, 'card_generation_batch_id');
    }

    /**
     * Kemajuan batch, dihitung dari baris log — bukan dari penghitung yang
     * di-increment job.
     *
     * Job render punya `tries = 3`, jadi penghitung yang dinaikkan setiap kali
     * job selesai akan melewati totalnya sendiri ketika ada yang dicoba ulang,
     * dan menggantung di bawah total ketika ada job yang mati tanpa sempat
     * menaikkannya. Menghitung ulang dari status baris membuat kedua kasus itu
     * mustahil: satu log punya satu status, apa pun yang terjadi pada jobnya.
     *
     * @return array{total: int, selesai: int, gagal: int, persen: int, status: string}
     */
    public function progres(): array
    {
        // `acrossSchools()`, bukan relasi `logs()`: `CardGenerationLog` memakai
        // global scope `school`, jadi menghitung lewat relasi mengembalikan nol
        // begitu batch yang dibuka milik sekolah selain yang aktif di sesi —
        // dan itu justru keadaan normal bagi super admin.
        $perStatus = CardGenerationLog::acrossSchools()
            ->where('card_generation_batch_id', $this->id)
            ->selectRaw('status, count(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        $selesai = (int) $perStatus->get('completed', 0);
        $gagal = (int) $perStatus->get('failed', 0);
        $beres = $selesai + $gagal;

        // `total` yang nol hanya mungkin kalau batch dibuat tanpa satu pun
        // siswa; menyebutnya 100% lebih jujur daripada membagi dengan nol.
        $persen = $this->total > 0
            ? (int) floor(min($beres, $this->total) / $this->total * 100)
            : 100;

        return [
            'total' => $this->total,
            'selesai' => $selesai,
            'gagal' => $gagal,
            'persen' => $persen,
            'status' => $beres >= $this->total ? ($gagal > 0 ? 'failed' : 'completed') : 'processing',
        ];
    }
}
