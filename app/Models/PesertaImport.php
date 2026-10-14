<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPenyelenggara;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Ringkasan satu kali impor peserta, termasuk baris yang gagal.
 */
class PesertaImport extends Model
{
    use BelongsToPenyelenggara, HasUlids;

    protected $fillable = ['penyelenggara_id', 'program_id', 'filename', 'total', 'berhasil', 'gagal', 'errors', 'oleh'];

    protected function casts(): array
    {
        return ['errors' => 'array'];
    }
}
