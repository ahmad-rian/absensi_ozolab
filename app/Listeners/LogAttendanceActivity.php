<?php

namespace App\Listeners;

use App\Events\StudentCheckedIn;
use App\Events\StudentCheckedOut;
use Illuminate\Support\Facades\Log;

class LogAttendanceActivity
{
    public function handle(StudentCheckedIn|StudentCheckedOut $event): void
    {
        $attendance = $event->attendance;
        $student = $attendance->student;

        Log::channel('daily')->info('Attendance recorded', [
            'student_id' => $student->id,
            'student_name' => $student->full_name,
            'type' => $attendance->type->value,
            'status' => $attendance->status->value,
            'date' => $attendance->attendance_date->format('Y-m-d'),
            'time' => $attendance->recorded_at?->format('H:i:s'),
        ]);
    }
}
