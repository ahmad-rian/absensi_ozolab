<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\PrayerType;
use App\Models\PrayerAttendance;
use App\Models\Student;
use App\Models\User;
use App\Support\PrayerSchedule;
use App\Support\PrayerSettings;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Absen sholat berjamaah — sekali check-in per siswa per hari PER JENIS sholat,
 * di dalam jendela yang diatur per sekolah (Dhuha default 07:30–09:00, Dzuhur
 * default 11:00–13:00).
 *
 * Jenis sholat ditentukan dari JAM scan, bukan dari link: satu perangkat di
 * mushola melayani keduanya dan petugas tidak bisa salah mode. Kedua jendela
 * dijamin tidak tumpang tindih oleh validasi di PengaturanController.
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
     * @param  ?PrayerType  $type  null = deteksi otomatis dari jam scan.
     *                             Diisi hanya oleh pencatatan manual.
     * @return array{success: bool, attendance: ?PrayerAttendance, message: string}
     */
    public function record(
        Student $student,
        ?User $recordedBy = null,
        ?string $deviceId = null,
        ?Carbon $timestamp = null,
        ?PrayerType $type = null,
    ): array {
        $timestamp = $timestamp ? SchoolTime::toLocal($timestamp) : SchoolTime::now();
        $date = $timestamp->toDateString();

        $school = $student->school;

        if (! $school) {
            return $this->fail('Siswa belum terhubung ke sekolah mana pun.');
        }

        $schedule = PrayerSchedule::for($school);

        if (! $schedule->anyEnabled()) {
            return $this->fail('Absen sholat belum diaktifkan untuk sekolah ini.');
        }

        if (! $this->scheduleResolver->isSchoolDay($student, $timestamp)) {
            return $this->fail('Tidak ada jadwal aktif untuk hari ini.');
        }

        if (! $schedule->covers($student)) {
            return $this->fail('Absen sholat hanya untuk siswa beragama Islam.');
        }

        $settings = $this->resolveSettings($schedule, $type, $timestamp);

        if ($settings === null) {
            if ($type !== null) {
                return $this->fail('Absen '.$type->label().' belum diaktifkan untuk sekolah ini.');
            }

            // Deteksi otomatis gagal: sebutkan SEMUA jendela yang aktif supaya
            // petugas tahu harus scan jam berapa.
            return $this->fail('Absen sholat dibuka pukul '.$schedule->windowsSentence().'.');
        }

        // Jenis eksplisit yang jamnya di luar jendelanya sendiri tetap ditolak,
        // supaya pencatatan manual tidak bisa memalsukan jam.
        if (! $settings->isWithinWindow($timestamp)) {
            return $this->fail(
                'Absen '.$settings->type->label().' dibuka pukul '
                .$settings->displayStart().' s/d '.$settings->displayEnd().'.'
            );
        }

        if ($existing = $this->existingRecord($student, $date, $settings->type)) {
            return $this->fail($this->alreadyRecordedMessage($settings->type, $existing));
        }

        try {
            $attendance = PrayerAttendance::create([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'prayer_date' => $date,
                'prayer_type' => $settings->type,
                'status' => AttendanceStatus::Hadir,
                'recorded_at' => $timestamp,
                'recorded_by' => $recordedBy?->id,
                // Device id default dibedakan per jenis supaya laporan bisa
                // membedakan sumber scan; Dzuhur menahan nilai lama.
                'device_id' => $deviceId ?? $settings->type->deviceId(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->fail($this->alreadyRecordedMessage($settings->type, null));
        }

        return [
            'success' => true,
            'attendance' => $attendance,
            'message' => 'Absen '.$settings->type->label().' berhasil dicatat.',
        ];
    }

    /**
     * Jenis eksplisit dipakai apa adanya (kalau aktif); kalau null, jam scan
     * yang menentukan.
     */
    private function resolveSettings(PrayerSchedule $schedule, ?PrayerType $type, Carbon $timestamp): ?PrayerSettings
    {
        if ($type !== null) {
            $settings = $schedule->get($type);

            return $settings->enabled ? $settings : null;
        }

        return $schedule->resolveFor($timestamp);
    }

    private function existingRecord(Student $student, string $date, PrayerType $type): ?PrayerAttendance
    {
        // SQLite menyimpan kolom `date` beserta komponen waktu, jadi
        // perbandingannya wajib lewat whereDate, bukan where biasa.
        return PrayerAttendance::where('student_id', $student->id)
            ->whereDate('prayer_date', $date)
            ->where('prayer_type', $type->value)
            ->first();
    }

    private function alreadyRecordedMessage(PrayerType $type, ?PrayerAttendance $existing): string
    {
        // `recorded_at` disimpan sebagai jam dinding sekolah (lihat SchoolTime),
        // jadi diformat apa adanya tanpa konversi zona waktu.
        $at = $existing?->recorded_at
            ? ' pukul '.$existing->recorded_at->format('H:i')
            : '';

        return 'Sudah absen '.$type->label().' hari ini'.$at.'.';
    }

    /**
     * @return array{success: false, attendance: null, message: string}
     */
    private function fail(string $message): array
    {
        return ['success' => false, 'attendance' => null, 'message' => $message];
    }
}
