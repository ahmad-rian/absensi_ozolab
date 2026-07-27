<?php

namespace App\Services\Attendance;

use App\Models\Student;

/**
 * Pencarian siswa dari hasil scan, dibatasi ke satu sekolah.
 *
 * Urutan QR token → NISN → NIS disengaja: NIS bisa sama antar sekolah, jadi
 * dipakai terakhir dan tetap di-scope `school_id`.
 */
class StudentLookup
{
    public function find(string $token, string $schoolId): ?Student
    {
        $token = trim($token);

        if ($token === '') {
            return null;
        }

        foreach (['qr_token', 'nisn', 'nis'] as $column) {
            $student = Student::where($column, $token)
                ->where('school_id', $schoolId)
                ->where('is_active', true)
                ->with('classroom')
                ->first();

            if ($student) {
                return $student;
            }
        }

        return null;
    }
}
