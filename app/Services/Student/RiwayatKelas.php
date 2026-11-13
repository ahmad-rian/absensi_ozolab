<?php

namespace App\Services\Student;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentClassHistory;

/**
 * Satu-satunya penulis `student_class_histories`.
 *
 * Dipanggil dari layar kenaikan kelas dan dari StudentObserver (edit manual,
 * impor), supaya riwayat lengkap dari jalur mana pun kelas siswa berubah.
 * Semua operasinya idempoten: dipanggil dua kali dengan nilai yang sama tidak
 * menggandakan atau mengubah apa pun.
 */
class RiwayatKelas
{
    /**
     * Catat bahwa siswa menempati `$kelasBaruId` pada tahun ajaran kelas itu.
     *
     * Hanya untuk kelas milik tahun ajaran AKTIF: memindah siswa ke kelas tahun
     * lama bukan kenaikan kelas dan tidak boleh membuka riwayat "berjalan" di
     * tahun yang sudah lewat. Sekolah tanpa tahun aktif tidak dicatat sama sekali.
     *
     * `$kelasLamaId` dipakai menambal riwayat tahun sebelumnya bila belum ada —
     * siswa yang didaftarkan setelah backfill migrasi tidak punya baris riwayat,
     * dan tanpa tambalan ini keanggotaan kelas lamanya hilang saat naik kelas.
     */
    public function catat(Student $student, ?string $kelasBaruId, ?string $kelasLamaId = null): void
    {
        $kelasBaru = $this->kelas($kelasBaruId, $student->school_id);

        if (! $kelasBaru || ! $kelasBaru->academicYear?->is_active) {
            return;
        }

        $tahunBaru = $kelasBaru->academic_year_id;
        $kelasLama = $kelasLamaId !== $kelasBaruId ? $this->kelas($kelasLamaId, $student->school_id) : null;

        if ($kelasLama && $kelasLama->academic_year_id && $kelasLama->academic_year_id !== $tahunBaru) {
            StudentClassHistory::query()
                ->withoutGlobalScope('school')
                ->firstOrCreate(
                    ['student_id' => $student->id, 'academic_year_id' => $kelasLama->academic_year_id],
                    ['school_id' => $student->school_id, 'classroom_id' => $kelasLama->id, 'is_current' => false],
                );
        }

        StudentClassHistory::query()
            ->withoutGlobalScope('school')
            ->where('student_id', $student->id)
            ->where('academic_year_id', '!=', $tahunBaru)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        StudentClassHistory::query()
            ->withoutGlobalScope('school')
            ->updateOrCreate(
                ['student_id' => $student->id, 'academic_year_id' => $tahunBaru],
                ['school_id' => $student->school_id, 'classroom_id' => $kelasBaru->id, 'is_current' => true],
            );
    }

    /**
     * Siswa lulus: kelas terakhirnya tetap tercatat, hanya tidak lagi "berjalan".
     */
    public function tutup(Student $student): void
    {
        $kelas = $this->kelas($student->classroom_id, $student->school_id);

        if ($kelas && $kelas->academic_year_id) {
            StudentClassHistory::query()
                ->withoutGlobalScope('school')
                ->firstOrCreate(
                    ['student_id' => $student->id, 'academic_year_id' => $kelas->academic_year_id],
                    ['school_id' => $student->school_id, 'classroom_id' => $kelas->id, 'is_current' => false],
                );
        }

        StudentClassHistory::query()
            ->withoutGlobalScope('school')
            ->where('student_id', $student->id)
            ->where('is_current', true)
            ->update(['is_current' => false]);
    }

    private function kelas(?string $id, ?string $schoolId): ?Classroom
    {
        if (! $id) {
            return null;
        }

        return Classroom::query()
            ->withoutGlobalScope('school')
            ->with(['academicYear' => fn ($q) => $q->withoutGlobalScope('school')])
            ->where('school_id', $schoolId)
            ->whereKey($id)
            ->first();
    }
}
