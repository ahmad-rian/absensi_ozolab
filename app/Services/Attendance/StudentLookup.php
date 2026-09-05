<?php

namespace App\Services\Attendance;

use App\Models\Student;
use App\Support\ScanRejectionLog;

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
    /**
     * Bentuk token QR dari QrTokenGenerator: `{identitas}.{24 hex}`.
     *
     * Identitas berasal dari NISN atau NIS, jadi bisa memuat huruf dan strip
     * (lihat QrTokenGeneratorTest yang memakai "NIS-99"). Tanda tangannya selalu
     * 24 karakter heksadesimal huruf kecil.
     */
    private const BENTUK_QR = '/[A-Za-z0-9_-]+\.[0-9a-f]{24}/';

    public function findByQrToken(string $token, string $schoolId): ?Student
    {
        $token = trim($token);

        if ($token === '') {
            return null;
        }

        if ($siswa = $this->cocokkanQrPersis($token, $schoolId)) {
            return $siswa;
        }

        // Percobaan kedua untuk bacaan yang kotor. Pembaca kartu di lapangan
        // terbukti menyisipkan karakter sampah — insiden RFID di deployment ini
        // menghasilkan UID berakhiran huruf `T` dan `P` yang tidak pernah
        // dikirim kartunya.
        //
        // Jalur RFID kebal karena normalizeRfidUid() membuang semua
        // non-alfanumerik. Jalur QR TIDAK BOLEH memakai normalisasi itu: ia akan
        // menghapus titik pemisah dan merusak tokennya. Yang dilakukan di sini
        // adalah menarik token BERBENTUK SAH dari bacaan berisik, lalu tetap
        // mencocokkannya PERSIS. Tidak ada LIKE, tidak ada awalan, tidak ada
        // kemiripan — bacaan yang bukan token utuh tetap ditolak.
        $bersih = self::extractQrToken($token);

        if ($bersih === null || $bersih === $token) {
            return null;
        }

        $siswa = $this->cocokkanQrPersis($bersih, $schoolId);

        if ($siswa) {
            ScanRejectionLog::diselamatkan($siswa->school, $token, $bersih);
        }

        return $siswa;
    }

    /**
     * Token berbentuk sah pertama yang ada di dalam bacaan, atau null.
     */
    public static function extractQrToken(string $raw): ?string
    {
        return preg_match(self::BENTUK_QR, trim($raw), $cocok) === 1 ? $cocok[0] : null;
    }

    private function cocokkanQrPersis(string $token, string $schoolId): ?Student
    {
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
