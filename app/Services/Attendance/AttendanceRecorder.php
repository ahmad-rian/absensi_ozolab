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
use App\Support\SchoolTime;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;

class AttendanceRecorder
{
    public function __construct(
        private readonly ScheduleResolver $scheduleResolver,
    ) {}

    /**
     * Catat absensi. Jika `$type` null, tipe ditentukan server dari jendela
     * waktu pada jadwal — client tidak pernah boleh memutuskan masuk/pulang.
     *
     * @return array{success: bool, attendance: ?Attendance, message: string}
     */
    public function record(
        Student $student,
        ?AttendanceType $type = null,
        ?User $recordedBy = null,
        ?string $deviceId = null,
        ?Carbon $timestamp = null,
        ?AttendanceStatus $status = null,
        ?string $notes = null,
    ): array {
        $timestamp = $timestamp ? SchoolTime::toLocal($timestamp) : SchoolTime::now();
        $date = $timestamp->toDateString();

        $schedule = $this->scheduleResolver->resolve($student, $timestamp);

        if (! $schedule) {
            return $this->fail('Tidak ada jadwal aktif untuk hari ini.');
        }

        if ($type === null) {
            $resolved = $this->resolveType($schedule, $timestamp);

            if ($resolved['type'] === null) {
                return $this->fail($resolved['message']);
            }

            $type = $resolved['type'];
        }

        if ($existing = $this->existingRecord($student, $date, $type)) {
            return $this->fail($this->alreadyRecordedMessage($type, $existing));
        }

        $status ??= $this->determineStatus($type, $timestamp, $schedule);

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
                'notes' => $notes,
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->fail($this->alreadyRecordedMessage($type, null));
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

    /**
     * Tentukan masuk/pulang murni dari jam scan terhadap jadwal hari itu.
     *
     * 06:00 ── masuk ── 13:00 ── pulang ── 18:00
     * Sebelum jendela masuk dan sesudah jendela pulang, scan ditolak.
     *
     * @return array{type: ?AttendanceType, message: string}
     */
    public function resolveType(AttendanceSchedule $schedule, Carbon $timestamp): array
    {
        $time = SchoolTime::toLocal($timestamp)->format('H:i:s');

        if ($time < $schedule->timeOf('check_in_start')) {
            return [
                'type' => null,
                'message' => 'Belum waktunya absen. Absen masuk dibuka pukul '.$schedule->displayTimeOf('check_in_start').'.',
            ];
        }

        if ($time < $schedule->timeOf('check_out_start')) {
            return ['type' => AttendanceType::CheckIn, 'message' => ''];
        }

        if ($time <= $schedule->timeOf('check_out_end')) {
            return ['type' => AttendanceType::CheckOut, 'message' => ''];
        }

        return [
            'type' => null,
            'message' => 'Jam absensi sudah tutup pukul '.$schedule->displayTimeOf('check_out_end').'.',
        ];
    }

    private function existingRecord(Student $student, string $date, AttendanceType $type): ?Attendance
    {
        return Attendance::where('student_id', $student->id)
            ->whereDate('attendance_date', $date)
            ->where('type', $type)
            ->first();
    }

    private function alreadyRecordedMessage(AttendanceType $type, ?Attendance $existing): string
    {
        $label = $type === AttendanceType::CheckIn ? 'masuk' : 'pulang';
        // `recorded_at` disimpan sebagai jam dinding sekolah (lihat SchoolTime),
        // jadi diformat apa adanya tanpa konversi zona waktu.
        $at = $existing?->recorded_at
            ? ' pukul '.$existing->recorded_at->format('H:i')
            : '';

        return 'Sudah absen '.$label.' hari ini'.$at.'.';
    }

    /**
     * @return array{success: false, attendance: null, message: string}
     */
    private function fail(string $message): array
    {
        return ['success' => false, 'attendance' => null, 'message' => $message];
    }

    private function determineStatus(
        AttendanceType $type,
        Carbon $timestamp,
        AttendanceSchedule $schedule,
    ): AttendanceStatus {
        if ($type === AttendanceType::CheckOut) {
            return AttendanceStatus::Hadir;
        }

        if ($timestamp->format('H:i:s') > $schedule->timeOf('late_threshold')) {
            return AttendanceStatus::Terlambat;
        }

        return AttendanceStatus::Hadir;
    }
}
