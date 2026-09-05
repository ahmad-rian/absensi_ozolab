<?php

namespace App\Observers;

use App\Jobs\SyncStudentDriveFolderJob;
use App\Models\CardGenerationLog;
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

    /**
     * Nilai yang ikut menyusun NAMA BERKAS `{slug-nama}-{nis}-{jenis}.png`.
     *
     * Kelas sengaja tidak masuk: ia menentukan letak folder, bukan nama berkas
     * di dalamnya. Membedakan keduanya yang membuat kenaikan kelas satu angkatan
     * — ratusan job sekaligus di antrean yang dipakai bersama puluhan situs —
     * tidak menambah satu pun panggilan Drive untuk menyelaraskan nama.
     */
    private const DRIVE_FILE_COLUMNS = ['nis', 'full_name'];

    public function updated(Student $student): void
    {
        if (! $student->wasChanged(self::DRIVE_PATH_COLUMNS)) {
            return;
        }

        $atributLama = [];

        if (! $student->drive_folder_id) {
            // Dulu keadaan ini berarti menyerah, dengan alasan "siswa ini belum
            // pernah menghasilkan berkas". Itu tidak selalu benar: siswa yang
            // terdaftar sebelum kolom drive_folder_id ada, atau yang lewat
            // /quick-regis, bisa punya folder berisi kartu dan pas foto yang
            // idnya tidak pernah tercatat. Untuk mereka, mengganti nama berarti
            // generate berikutnya membuat folder KEDUA dan isi yang lama jadi
            // yatim — persis keluhan "regenerate tidak menimpa".
            //
            // Hanya siswa yang PUNYA jejak berkas Drive yang dikejar. Tanpa
            // penyaring ini, satu kenaikan kelas seangkatan mengantrekan ratusan
            // job yang seluruhnya tidak menemukan apa-apa, di antrean yang
            // dipakai bersama puluhan situs lain.
            if (! $this->punyaJejakDrive($student)) {
                return;
            }

            // Nilai lama dibawa serta: job memuat siswa dalam keadaan BARU, jadi
            // hanya dari sini ia bisa tahu nama folder yang harus dicari.
            $atributLama = [
                'id' => $student->id,
                'nis' => $student->getOriginal('nis'),
                'full_name' => $student->getOriginal('full_name'),
                'classroom_id' => $student->getOriginal('classroom_id'),
            ];
        }

        SyncStudentDriveFolderJob::dispatch(
            $student->id,
            $student->wasChanged(self::DRIVE_FILE_COLUMNS),
            $atributLama,
        );
    }

    /**
     * Pernahkah siswa ini menghasilkan berkas yang benar-benar mendarat di Drive?
     */
    private function punyaJejakDrive(Student $student): bool
    {
        if ($student->photo_drive_file_id) {
            return true;
        }

        return CardGenerationLog::withoutGlobalScope('school')
            ->where('student_id', $student->id)
            ->whereNotNull('drive_file_id')
            ->exists();
    }
}
