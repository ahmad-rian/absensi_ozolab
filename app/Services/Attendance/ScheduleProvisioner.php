<?php

namespace App\Services\Attendance;

use App\Models\AttendanceSchedule;

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
}
