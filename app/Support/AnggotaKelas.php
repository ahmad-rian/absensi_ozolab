<?php

namespace App\Support;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentClassHistory;
use Illuminate\Database\Eloquent\Builder;

/**
 * Siapa anggota sebuah kelas.
 *
 * Kelas tahun ajaran berjalan: siswa yang `classroom_id`-nya menunjuk ke sana.
 * Kelas tahun ajaran LAMA: siswanya sudah naik kelas, jadi penunjuk itu sudah
 * pindah — anggotanya dibaca dari riwayat kelas. Tanpa pembedaan ini rekap dan
 * absen kelas tahun lalu kosong begitu kenaikan kelas diterapkan.
 */
class AnggotaKelas
{
    /**
     * Kelas dianggap "lama" hanya bila sekolahnya punya tahun ajaran aktif dan
     * kelas ini bukan miliknya. Sekolah yang belum pernah mengaktifkan tahun
     * ajaran tidak punya kelas lama: semua kelasnya masih kelas berjalan.
     */
    public static function kelasLama(Classroom $kelas): bool
    {
        $tahunAktif = AcademicYear::query()
            ->withoutGlobalScope('school')
            ->where('school_id', $kelas->school_id)
            ->where('is_active', true)
            ->pluck('id');

        return $tahunAktif->isNotEmpty() && ! $tahunAktif->contains($kelas->academic_year_id);
    }

    /**
     * Query siswa anggota kelas.
     *
     * Untuk kelas lama, siswa yang sudah lulus (nonaktif) tetap ikut — mereka
     * memang anggota kelas itu pada tahunnya. Siswa yang penunjuknya masih ke
     * kelas lama (belum dinaikkan) juga ikut supaya tidak ada yang hilang.
     *
     * @return Builder<Student>
     */
    public static function query(Classroom $kelas, bool $hanyaAktif = true): Builder
    {
        $query = Student::query()
            ->withoutGlobalScope('school')
            ->where('school_id', $kelas->school_id);

        if (! self::kelasLama($kelas)) {
            return $query
                ->where('classroom_id', $kelas->id)
                ->when($hanyaAktif, fn (Builder $q) => $q->where('is_active', true));
        }

        $dariRiwayat = StudentClassHistory::query()
            ->withoutGlobalScope('school')
            ->where('classroom_id', $kelas->id)
            ->select('student_id');

        return $query->where(fn (Builder $q) => $q
            ->whereIn('id', $dariRiwayat)
            ->orWhere('classroom_id', $kelas->id));
    }
}
