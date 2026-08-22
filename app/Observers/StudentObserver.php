<?php

namespace App\Observers;

use App\Jobs\SyncStudentDriveFolderJob;
use App\Models\Student;

/**
 * Menjaga Drive tetap sejalan dengan baris siswa.
 *
 * Letak folder Drive seorang siswa diturunkan dari kelas, NIS, dan namanya. Ketiga
 * nilai itu berubah lewat banyak jalur — form admin, impor, kenaikan kelas
 * massal, penyeragaman huruf besar — dan tidak satu pun dari jalur itu tahu soal
 * Drive. Menaruh aturannya di sini berarti semuanya ikut, termasuk jalur baru
 * yang belum ada.
 */
class StudentObserver
{
    /** Nilai yang ikut menyusun jalur folder `{Kelas}/{NIS - Nama}`. */
    private const DRIVE_PATH_COLUMNS = ['classroom_id', 'nis', 'full_name'];

    public function updated(Student $student): void
    {
        if (! $student->drive_folder_id) {
            return;
        }

        if (! $student->wasChanged(self::DRIVE_PATH_COLUMNS)) {
            return;
        }

        SyncStudentDriveFolderJob::dispatch($student->id);
    }
}
