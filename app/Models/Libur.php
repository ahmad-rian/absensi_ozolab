<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPenyelenggara;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Hari tanpa sesi aktif dalam satu program.
 */
class Libur extends Model
{
    use BelongsToPenyelenggara, HasUlids;

    protected $fillable = ['penyelenggara_id', 'program_id', 'tanggal', 'keterangan'];

    protected function setTanggalAttribute(mixed $value): void
    {
        $this->attributes['tanggal'] = substr((string) $value, 0, 10);
    }

    public function getTanggalAttribute(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 10);
    }
}
