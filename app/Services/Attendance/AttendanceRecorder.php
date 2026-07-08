<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Events\StudentCheckedIn;
use App\Events\StudentCheckedOut;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;

class AttendanceRecorder
{
    public function __construct(
        private readonly ScheduleResolver $scheduleResolver,
    ) {}

    /**
     * @return array{success: bool, attendance: ?Attendance, message: string}
     */
    public function record(
        Student $student,
        AttendanceType $type,
        ?User $recordedBy = null,
        ?string $deviceId = null,
        ?Carbon $timestamp = null,
    ): array {
        $timestamp ??= Carbon::now('Asia/Jakarta');
        // Ensure timestamp is in Jakarta timezone for correct late threshold comparison
        $timestamp = $timestamp->setTimezone('Asia/Jakarta');
        $date = $timestamp->toDateString();

        $schedule = $this->scheduleResolver->resolve($student, $timestamp);

        if (! $schedule) {
            return [
                'success' => false,
                'attendance' => null,
                'message' => 'Tidak ada jadwal aktif untuk hari ini.',
            ];
        }

        $status = $this->determineStatus($type, $timestamp, $schedule);

        try {
            $attendance = Attendance::create([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'attendance_date' => $date,
                'type' => $type,
                'status' => $status,
                'recorded_at' => $timestamp,
                'recorded_by' => $recordedBy?->id,
                'device_id' => $deviceId,
            ]);
        } catch (UniqueConstraintViolationException) {
            return [
                'success' => false,
                'attendance' => null,
                'message' => 'Siswa sudah melakukan '.($type === AttendanceType::CheckIn ? 'check-in' : 'check-out').' hari ini.',
            ];
        }

        if ($type === AttendanceType::CheckIn) {
            event(new StudentCheckedIn($attendance));
        } else {
            event(new StudentCheckedOut($attendance));
        }

        return [
            'success' => true,
            'attendance' => $attendance,
            'message' => 'Absensi berhasil dicatat.',
        ];
    }

    private function determineStatus(
        AttendanceType $type,
        Carbon $timestamp,
        AttendanceSchedule $schedule,
    ): AttendanceStatus {
        if ($type === AttendanceType::CheckOut) {
            return AttendanceStatus::Hadir;
        }

        $time = $timestamp->format('H:i:s');
        $lateThreshold = $schedule->late_threshold;

        if ($time > $lateThreshold) {
            return AttendanceStatus::Terlambat;
        }

        return AttendanceStatus::Hadir;
    }
}
