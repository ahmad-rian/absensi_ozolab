<?php

namespace App\Support;

use App\Models\Student;
use Illuminate\Support\Str;

/**
 * Konvensi letak pas foto siswa di disk `public`.
 *
 * Diangkat dari `RegisterStudentCardsJob` supaya jalur unggah dari halaman
 * siswa memakai konvensi yang sama persis, bukan konvensi kedua yang bisa
 * menyimpang. `PhotoSheetGeneratorService` dan `AlbumGeneratorService` membaca
 * `photo_path` apa adanya, jadi kedua jalur harus menghasilkan bentuk yang sama.
 */
class StudentPhotoStorage
{
    /**
     * `schools.id` dan `students.id` keduanya ULID — dulu diformat dengan `%d`
     * sehingga runtuh jadi `1` dan seluruh sekolah menulis ke folder yang sama.
     * Komponen acak membuat nama berkas tidak bisa ditebak dari nama siswa.
     */
    public static function path(string $schoolId, Student $student): string
    {
        return sprintf('photos/students/%s/%s-%s.png', $schoolId, $student->id, Str::random(16));
    }
}
