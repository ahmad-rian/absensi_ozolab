<?php

namespace App\Services\Attendance;

use App\Models\AttendanceSchedule;
use App\Models\Student;
use Carbon\Carbon;

class ScheduleResolver
{
    public function resolve(Student $student, ?Carbon $date = null): ?AttendanceSchedule
    {
        $date ??= Carbon::now();
        $dayOfWeek = $date->dayOfWeekIso; // 1=Monday ... 7=Sunday

        // Try class-specific schedule first
        $schedule = AttendanceSchedule::where('classroom_id', $student->classroom_id)
            ->where('school_id', $student->school_id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();

        // Fall back to global schedule (classroom_id is null)
        if (! $schedule) {
            $schedule = AttendanceSchedule::whereNull('classroom_id')
                ->where('school_id', $student->school_id)
                ->where('day_of_week', $dayOfWeek)
                ->where('is_active', true)
                ->first();
        }

        return $schedule;
    }

    public function isSchoolDay(Student $student, ?Carbon $date = null): bool
    {
        return $this->resolve($student, $date) !== null;
    }
}
