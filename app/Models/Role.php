<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasUlids;

    /** Peran staf workspace General buatan super admin (template modul). */
    public const LINGKUP_GENERAL = 'general';

    public function scopePeranStaf($query)
    {
        return $query->where('lingkup', self::LINGKUP_GENERAL);
    }

    /**
     * Peran sekolah & bawaan — peran staf General tidak pernah muncul di
     * halaman Role & Hak Akses sekolah.
     */
    public function scopeBukanPeranStaf($query)
    {
        return $query->where(fn ($q) => $q->whereNull('lingkup')->orWhere('lingkup', '!=', self::LINGKUP_GENERAL));
    }
}
