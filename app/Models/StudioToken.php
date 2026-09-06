<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Kredensial satu pemasangan Tyas Studio.
 *
 * SENGAJA tidak memakai `BelongsToSchool`. Global scope itu membaca sekolah
 * milik user yang sedang login, sedangkan token justru dicari ketika TIDAK ada
 * user sama sekali — scope-nya akan mati dan memberi rasa aman yang palsu.
 * Penyaringan sekolah di sini selalu eksplisit, di pemanggilnya.
 */
class StudioToken extends Model
{
    use HasUlids;

    protected $fillable = [
        'school_id',
        'name',
        'token_hash',
        'last_used_at',
        'revoked_at',
        'created_by',
    ];

    /**
     * `token_hash` tidak pernah ikut serialisasi.
     *
     * Ia bukan token mentahnya, tapi ia satu-satunya bahan untuk menebak lewat
     * kamus — dan tidak ada satu pun layar yang butuh melihatnya.
     */
    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Token mentah baru, sudah tersimpan sebagai hash.
     *
     * Nilai kembaliannya adalah SATU-SATUNYA kesempatan melihat token mentah;
     * setelah ini hanya hash-nya yang ada. Jangan pernah menuliskannya ke log.
     *
     * @return array{0: self, 1: string}
     */
    public static function terbitkan(string $name, ?string $schoolId, ?string $createdBy = null): array
    {
        $mentah = 'tst_'.Str::random(48);

        $token = self::create([
            'school_id' => $schoolId,
            'name' => $name,
            'token_hash' => self::hash($mentah),
            'created_by' => $createdBy,
        ]);

        return [$token, $mentah];
    }

    public static function hash(string $mentah): string
    {
        return hash('sha256', $mentah);
    }

    /**
     * Token yang masih berlaku untuk nilai mentah ini, atau null.
     *
     * Pencocokannya lewat hash, jadi tidak ada perbandingan string rahasia yang
     * bisa dibocorkan lewat waktu eksekusi.
     */
    public static function untukMentah(string $mentah): ?self
    {
        if ($mentah === '') {
            return null;
        }

        return self::where('token_hash', self::hash($mentah))
            ->whereNull('revoked_at')
            ->first();
    }

    public function sudahDicabut(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Apakah token ini boleh menyentuh sekolah tersebut.
     *
     * `school_id` null berarti lintas sekolah.
     */
    public function bolehSekolah(?string $schoolId): bool
    {
        return $this->school_id === null || $this->school_id === $schoolId;
    }

    /**
     * Batasi query ke sekolah yang boleh disentuh token ini.
     *
     * Dipakai di SETIAP query endpoint Studio. Global scope `school` mati di
     * jalur bertoken — tanpa pembatas ini, satu token akan melihat seluruh
     * siswa dari seluruh sekolah.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function batasi(Builder $query, string $kolom = 'school_id'): Builder
    {
        return $this->school_id === null
            ? $query
            : $query->where($kolom, $this->school_id);
    }
}
