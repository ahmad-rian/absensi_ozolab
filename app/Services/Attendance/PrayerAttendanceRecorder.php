<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\PrayerAttendance;
use App\Models\Student;
use App\Models\User;
use App\Support\PrayerSettings;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Absen sholat dzuhur — sekali check-in per siswa per hari, di dalam jendela
 * yang diatur per sekolah (default 11:00–13:00).
 *
 * Sengaja tidak menulis ke `attendances` dan tidak memancarkan event apa pun:
 * statistik absensi sekolah harus tetap bersih, dan orang tua tidak perlu
 * dikirimi notifikasi tiap sholat.
 */
class PrayerAttendanceRecorder
{
    public function __construct(
        private readonly ScheduleResolver $scheduleResolver,
    ) {}

    /**
     * @return array{success: bool, attendance: ?PrayerAttendance, message: string}
     */
    public function record(
        Student $student,
        ?User $recordedBy = null,
        ?string $deviceId = null,
        ?Carbon $timestamp = null,
    ): array {
        $timestamp = $timestamp ? SchoolTime::toLocal($timestamp) : SchoolTime::now();
        $date = $timestamp->toDateString();

        $school = $student->school;

        if (! $school) {
            return $this->fail('Siswa belum terhubung ke sekolah mana pun.');
        }

        $settings = PrayerSettings::for($school);

        if (! $settings->enabled) {
            return $this->fail('Absen sholat belum diaktifkan untuk sekolah ini.');
        }

        if (! $this->scheduleResolver->isSchoolDay($student, $timestamp)) {
            return $this->fail('Tidak ada jadwal aktif untuk hari ini.');
        }

        if (! $settings->covers($student)) {
            return $this->fail('Absen sholat hanya untuk siswa beragama Islam.');
        }

        if (! $settings->isWithinWindow($timestamp)) {
            return $this->fail(
                'Absen sholat dibuka pukul '.$settings->displayStart().' s/d '.$settings->displayEnd().'.'
            );
        }

        if ($existing = $this->existingRecord($student, $date)) {
            return $this->fail($this->alreadyRecordedMessage($existing));
        }

        try {
            $attendance = PrayerAttendance::create([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'prayer_date' => $date,
                'status' => AttendanceStatus::Hadir,
                'recorded_at' => $timestamp,
                'recorded_by' => $recordedBy?->id,
                'device_id' => $deviceId,
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->fail($this->alreadyRecordedMessage(null));
        }

        return [
            'success' => true,
            'attendance' => $attendance,
            'message' => 'Absen sholat berhasil dicatat.',
        ];
    }

    private function existingRecord(Student $student, string $date): ?PrayerAttendance
    {
        return PrayerAttendance::where('student_id', $student->id)
            ->whereDate('prayer_date', $date)
            ->first();
    }

    private function alreadyRecordedMessage(?PrayerAttendance $existing): string
    {
        // `recorded_at` disimpan sebagai jam dinding sekolah (lihat SchoolTime),
        // jadi diformat apa adanya tanpa konversi zona waktu.
        $at = $existing?->recorded_at
            ? ' pukul '.$existing->recorded_at->format('H:i')
            : '';

        return 'Sudah absen sholat hari ini'.$at.'.';
    }

    /**
     * @return array{success: false, attendance: null, message: string}
     */
    private function fail(string $message): array
    {
        return ['success' => false, 'attendance' => null, 'message' => $message];
    }
}
