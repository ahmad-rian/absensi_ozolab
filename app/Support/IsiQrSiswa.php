<?php

namespace App\Support;

use App\Models\School;
use App\Models\Student;

/**
 * Isi QR yang dicetak di kartu siswa.
 *
 * Bawaannya `qr_token` — kredensial acak yang tidak bisa ditebak. Sekolah yang
 * kartunya diminta berisi NISN polos (mis. supaya cocok dengan sistem lain)
 * menyalakan saklar `qr_isi_nisn`. NISN mudah ditebak, jadi saklar ini hanya
 * bisa diubah super admin dan bawaannya mati.
 */
class IsiQrSiswa
{
    public const SETTING = 'qr_isi_nisn';

    /** NISN nasional selalu 10 digit. */
    public const BENTUK_NISN = '/^\d{10}$/';

    public static function aktif(?School $school): bool
    {
        return (bool) $school?->getSetting(self::SETTING, false);
    }

    /**
     * NISN kalau saklar sekolah menyala dan NISN-nya sah, selain itu token QR.
     * Siswa tanpa NISN tetap memakai token supaya kartunya tetap bisa di-scan.
     */
    public static function untuk(Student $student): ?string
    {
        $nisn = trim((string) $student->nisn);

        if (preg_match(self::BENTUK_NISN, $nisn) === 1 && self::aktif($student->school)) {
            return $nisn;
        }

        return $student->qr_token;
    }
}
