<?php

namespace App\Services\Attendance;

use App\Models\AttendanceSchedule;
use App\Models\Classroom;

/**
 * Satu-satunya sumber jadwal absensi default.
 *
 * Sebelumnya nilai default tersebar (dan saling berbeda) di controller,
 * seeder, dan factory. Semua sekarang menarik dari sini / config.
 */
class ScheduleProvisioner
{
    /**
     * @return array{check_in_start: string, check_in_end: string, late_threshold: string, check_out_start: string, check_out_end: string}
     */
    public function defaultTimes(): array
    {
        return config('attendance.default_schedule');
    }

    /**
     * @return array<int, int>
     */
    public function activeDays(): array
    {
        return config('attendance.default_active_days', [1, 2, 3, 4, 5]);
    }

    /**
     * @return array<int, int>
     */
    public function inactiveDays(): array
    {
        return config('attendance.default_inactive_days', [6]);
    }

    /**
     * Pastikan sekolah punya jadwal global (classroom_id null) untuk tiap hari
     * kerja. Dengan `$force`, jam pada seluruh jadwal sekolah itu — termasuk
     * jadwal per kelas — ikut diseragamkan ke nilai default.
     *
     * @return array{created: int, updated: int}
     */
    public function provision(string $schoolId, bool $force = false): array
    {
        $times = $this->defaultTimes();
        $created = 0;

        foreach ([...$this->activeDays(), ...$this->inactiveDays()] as $day) {
            $exists = AttendanceSchedule::withoutGlobalScope('school')
                ->where('school_id', $schoolId)
                ->whereNull('classroom_id')
                ->where('day_of_week', $day)
                ->exists();

            if ($exists) {
                continue;
            }

            AttendanceSchedule::withoutGlobalScope('school')->create([
                ...$times,
                'school_id' => $schoolId,
                'classroom_id' => null,
                'day_of_week' => $day,
                'is_active' => in_array($day, $this->activeDays(), true),
            ]);

            $created++;
        }

        $updated = 0;

        if ($force) {
            $updated = AttendanceSchedule::withoutGlobalScope('school')
                ->where('school_id', $schoolId)
                ->update($times);
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * Salin jadwal khusus kelas dari kelas bernama sama di tahun ajaran lain.
     *
     * Dipakai saat kenaikan kelas: "8A" tahun ini mewarisi jadwal "8A" tahun
     * lalu, supaya siswa yang baru naik tidak diam-diam jatuh ke jadwal umum.
     * Kelas yang sudah punya jadwal sendiri tidak disentuh.
     *
     * @return int jumlah baris jadwal yang disalin
     */
    public function salinDariKelas(Classroom $tujuan): int
    {
        $punyaSendiri = AttendanceSchedule::withoutGlobalScope('school')
            ->where('classroom_id', $tujuan->id)
            ->exists();

        if ($punyaSendiri) {
            return 0;
        }

        $nama = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $tujuan->name)));

        $sumber = Classroom::withoutGlobalScope('school')
            ->where('school_id', $tujuan->school_id)
            ->where('academic_year_id', '!=', $tujuan->academic_year_id)
            ->whereIn('id', AttendanceSchedule::withoutGlobalScope('school')->whereNotNull('classroom_id')->select('classroom_id'))
            ->with(['academicYear' => fn ($q) => $q->withoutGlobalScope('school')])
            ->get()
            ->filter(fn (Classroom $k): bool => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $k->name))) === $nama)
            // Tahun ajaran paling baru yang menang.
            ->sortByDesc(fn (Classroom $k) => (string) $k->academicYear?->start_date)
            ->first();

        if (! $sumber) {
            return 0;
        }

        $jadwal = AttendanceSchedule::withoutGlobalScope('school')
            ->where('classroom_id', $sumber->id)
            ->get();

        foreach ($jadwal as $baris) {
            AttendanceSchedule::withoutGlobalScope('school')->create([
                'school_id' => $tujuan->school_id,
                'classroom_id' => $tujuan->id,
                'day_of_week' => $baris->day_of_week,
                'check_in_start' => $baris->check_in_start,
                'check_in_end' => $baris->check_in_end,
                'late_threshold' => $baris->late_threshold,
                'check_out_start' => $baris->check_out_start,
                'check_out_end' => $baris->check_out_end,
                'is_active' => $baris->is_active,
            ]);
        }

        return $jadwal->count();
    }
}
