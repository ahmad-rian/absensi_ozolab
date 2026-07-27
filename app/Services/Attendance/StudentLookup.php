<?php

namespace App\Services\Attendance;

use App\Models\Student;

/**
 * Pencarian siswa dari hasil scan, dibatasi ke satu sekolah.
 *
 * Hanya cocok pada `qr_token`. Fallback ke NIS/NISN sengaja dihapus: kolom itu
 * bisa ditebak (NIS 8 digit dengan rentang sempit), sehingga scanner publik
 * dulu bisa dipakai memalsukan kehadiran dan memanen PII siswa satu sekolah
 * tanpa akun sama sekali.
 */
class StudentLookup
{
    public function findByQrToken(string $token, string $schoolId): ?Student
    {
        $token = trim($token);

        if ($token === '') {
            return null;
        }

        return Student::where('qr_token', $token)
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->with('classroom')
            ->first();
    }
}
