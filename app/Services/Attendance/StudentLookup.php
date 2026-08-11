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

    /**
     * UID kartu RFID, dinormalkan ke huruf besar tanpa pemisah.
     *
     * Pembaca kartu mengetikkan UID dalam bentuk yang berbeda-beda — ada yang
     * memakai titik dua, ada yang huruf kecil — sedangkan yang tersimpan satu
     * bentuk saja. Normalisasi yang sama dipakai saat pendaftaran kartu.
     */
    public function findByRfidUid(string $uid, string $schoolId): ?Student
    {
        $uid = self::normalizeRfidUid($uid);

        if ($uid === '') {
            return null;
        }

        return Student::where('rfid_uid', $uid)
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->with('classroom')
            ->first();
    }

    public static function normalizeRfidUid(string $uid): string
    {
        return mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim($uid)) ?? '');
    }
}
