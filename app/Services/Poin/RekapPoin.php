<?php

namespace App\Services\Poin;

use App\Models\PoinSiswa;
use App\Models\Student;
use App\Support\PoinSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Total poin semester berjalan. Semua query memakai scope sekolah pengguna
 * yang login, kecuali `untukSiswa()` yang menyaring per siswa secara eksplisit
 * (dipakai juga oleh portal orang tua).
 */
class RekapPoin
{
    /**
     * Siswa yang punya poin di semester berjalan, terbesar dulu.
     *
     * @return Builder<Student>
     */
    public function perSiswa(PoinSettings $setup, ?string $kelas, ?string $cari, string $level): Builder
    {
        return Student::query()
            ->with('classroom:id,name')
            ->joinSub($this->agregat($setup), 'p', 'p.student_id', '=', 'students.id')
            ->select('students.*', 'p.poin_total', 'p.poin_terlambat', 'p.poin_alpa', 'p.poin_manual')
            ->when($kelas, fn ($q) => $q->where('students.classroom_id', $kelas))
            ->when($cari, fn ($q) => $q->where('students.full_name', 'like', '%'.addcslashes($cari, '%_\\').'%'))
            ->when($level !== '', fn ($q) => $this->saringLevel($q, $setup, $level))
            ->orderByDesc('p.poin_total')
            ->orderBy('students.full_name');
    }

    /**
     * Jumlah siswa per level warna.
     *
     * @return array{kuning: int, orange: int, merah: int, siswa: int}
     */
    public function ringkasanLevel(PoinSettings $setup): array
    {
        $hasil = ['kuning' => 0, 'orange' => 0, 'merah' => 0, 'siswa' => 0];

        foreach ($this->agregat($setup)->pluck('poin_total') as $total) {
            $hasil['siswa']++;
            $level = $setup->levelUntuk((int) $total);

            if ($level !== null) {
                $hasil[$level]++;
            }
        }

        return $hasil;
    }

    /**
     * Total, level, dan riwayat semester berjalan untuk satu siswa.
     *
     * @return array{total: int, level: ?string, semester_mulai: string, level_ambang: array<string, int>, riwayat: Collection<int, PoinSiswa>}
     */
    public function untukSiswa(Student $student, PoinSettings $setup): array
    {
        $riwayat = PoinSiswa::withoutGlobalScope('school')
            ->with('pencatat:id,name')
            ->where('school_id', $student->school_id)
            ->where('student_id', $student->id)
            ->where('tanggal', '>=', $setup->semesterMulai)
            ->orderByDesc('tanggal')
            ->latest()
            ->limit(200)
            ->get();

        $total = $this->totalSiswa($student, $setup);

        return [
            'total' => $total,
            'level' => $setup->levelUntuk($total),
            'semester_mulai' => $setup->semesterMulai,
            'level_ambang' => $setup->level,
            'riwayat' => $riwayat,
        ];
    }

    /**
     * Total semester berjalan saja — untuk ringkasan yang tidak butuh riwayat.
     */
    public function totalSiswa(Student $student, PoinSettings $setup): int
    {
        return (int) PoinSiswa::withoutGlobalScope('school')
            ->where('school_id', $student->school_id)
            ->where('student_id', $student->id)
            ->where('tanggal', '>=', $setup->semesterMulai)
            ->sum('poin');
    }

    /**
     * @return Builder<PoinSiswa>
     */
    private function agregat(PoinSettings $setup): Builder
    {
        return PoinSiswa::query()
            ->where('tanggal', '>=', $setup->semesterMulai)
            ->selectRaw('student_id, sum(poin) as poin_total')
            ->selectRaw("sum(case when sumber = 'TERLAMBAT' then 1 else 0 end) as poin_terlambat")
            ->selectRaw("sum(case when sumber = 'ALPA' then 1 else 0 end) as poin_alpa")
            ->selectRaw("sum(case when sumber = 'MANUAL' then 1 else 0 end) as poin_manual")
            ->groupBy('student_id');
    }

    /**
     * @param  Builder<Student>  $query
     */
    private function saringLevel(Builder $query, PoinSettings $setup, string $level): void
    {
        $ambang = $setup->level;

        match ($level) {
            'aman' => $query->where('p.poin_total', '<', $ambang['kuning']),
            'kuning' => $query->where('p.poin_total', '>=', $ambang['kuning'])->where('p.poin_total', '<', $ambang['orange']),
            'orange' => $query->where('p.poin_total', '>=', $ambang['orange'])->where('p.poin_total', '<', $ambang['merah']),
            default => $query->where('p.poin_total', '>=', $ambang['merah']),
        };
    }
}
