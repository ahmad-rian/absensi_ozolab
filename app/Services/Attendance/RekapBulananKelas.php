<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\Classroom;
use App\Models\Student;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Daftar hadir satu kelas dalam satu bulan: baris siswa × kolom hari sekolah,
 * seperti buku absen guru.
 *
 * Hari sekolah diambil dari jadwal absensi (hari yang punya jadwal aktif untuk
 * kelas ini atau jadwal umum). Sekolah tanpa jadwal memakai Senin–Jumat.
 */
class RekapBulananKelas
{
    private const SINGKAT = [
        'HADIR' => 'H',
        'TERLAMBAT' => 'T',
        'IZIN' => 'I',
        'SAKIT' => 'S',
        'ALPA' => 'A',
    ];

    private const NAMA_HARI = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];

    /**
     * @return array{
     *     bulan: string,
     *     hari: list<array{tanggal: string, tgl: int, nama: string, lewat: bool}>,
     *     baris: list<array{id: string, nama: string, nis: string|null, status: array<string, string>, jumlah: array<string, int>, persen: float|null}>
     * }
     */
    public function untuk(Classroom $classroom, string $bulan): array
    {
        $awal = Carbon::createFromFormat('Y-m-d', $bulan.'-01', SchoolTime::timezone())->startOfDay();
        $akhir = $awal->copy()->endOfMonth()->startOfDay();
        $hariIni = SchoolTime::today();

        $hariSekolah = $this->hariSekolah($classroom);
        $hari = collect(CarbonPeriod::create($awal, $akhir))
            ->filter(fn (Carbon $tgl) => in_array($tgl->dayOfWeekIso, $hariSekolah, true))
            ->map(fn (Carbon $tgl) => [
                'tanggal' => $tgl->toDateString(),
                'tgl' => $tgl->day,
                'nama' => self::NAMA_HARI[$tgl->dayOfWeekIso],
                'lewat' => $tgl->lessThanOrEqualTo($hariIni),
            ])
            ->values();

        $siswa = Student::query()
            ->where('classroom_id', $classroom->id)
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'nis', 'full_name']);

        $absen = Attendance::query()
            ->whereIn('student_id', $siswa->pluck('id'))
            ->where('type', AttendanceType::CheckIn->value)
            ->whereBetween('attendance_date', [$awal->toDateString(), $akhir->toDateString()])
            ->get(['student_id', 'attendance_date', 'status'])
            ->groupBy('student_id');

        $jumlahLewat = $hari->where('lewat', true)->count();

        $baris = $siswa->map(function (Student $student) use ($absen, $jumlahLewat): array {
            $status = collect($absen->get($student->id, []))
                ->mapWithKeys(fn (Attendance $a) => [
                    Carbon::parse($a->attendance_date)->toDateString() => self::SINGKAT[$a->status->value] ?? '?',
                ]);
            $jumlah = collect(self::SINGKAT)->mapWithKeys(fn (string $huruf) => [$huruf => $status->filter(fn ($s) => $s === $huruf)->count()]);
            $datang = $jumlah['H'] + $jumlah['T'];

            return [
                'id' => $student->id,
                'nama' => $student->full_name,
                'nis' => $student->nis,
                'status' => $status->all(),
                'jumlah' => $jumlah->all(),
                'persen' => $jumlahLewat > 0 ? round($datang / $jumlahLewat * 100, 1) : null,
            ];
        })->values()->all();

        return ['bulan' => $bulan, 'hari' => $hari->all(), 'baris' => $baris];
    }

    /**
     * @return list<int> hari ISO (1 = Senin)
     */
    private function hariSekolah(Classroom $classroom): array
    {
        $hari = AttendanceSchedule::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('classroom_id')->orWhere('classroom_id', $classroom->id))
            ->pluck('day_of_week')
            ->map(fn ($d) => (int) $d)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $hari !== [] ? $hari : [1, 2, 3, 4, 5];
    }
}
