<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\AcademicYearFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class AcademicYear extends Model
{
    /** @use HasFactory<AcademicYearFactory> */
    use BelongsToSchool, HasFactory, HasUlids;

    protected $fillable = [
        'school_id',
        'name',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Jadikan tahun ajaran ini satu-satunya yang aktif di sekolahnya.
     *
     * Tidak ada unique partial "satu is_active per sekolah" di DB, jadi
     * konsistensi dijaga di sini: menonaktifkan sisanya dan mengaktifkan yang
     * ini harus satu transaksi, kalau tidak kegagalan di tengah meninggalkan
     * sekolah tanpa tahun ajaran aktif sama sekali dan KelasController serta
     * dashboard kehilangan acuan.
     *
     * Global scope `school` sengaja dilepas: aktivasi juga dipanggil dari
     * konsol/queue yang tidak punya user login, dan di sana scope diam sehingga
     * UPDATE tanpa filter eksplisit akan menyapu seluruh tenant.
     */
    public function activate(): void
    {
        DB::transaction(function (): void {
            $siblings = static::query()
                ->withoutGlobalScope('school')
                ->whereKeyNot($this->getKey());

            // `where('school_id', null)` tidak pernah cocok di SQL; tahun ajaran
            // warisan yang belum bertenant harus dibandingkan dengan IS NULL.
            $this->school_id === null
                ? $siblings->whereNull('school_id')
                : $siblings->where('school_id', $this->school_id);

            $siblings->where('is_active', true)->update(['is_active' => false]);

            $this->forceFill(['is_active' => true])->save();
        });
    }
}
