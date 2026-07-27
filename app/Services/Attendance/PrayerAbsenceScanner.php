<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceType;
use App\Enums\PrayerType;
use App\Models\School;
use App\Models\Student;
use App\Support\PrayerSettings;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cari siswa yang berturut-turut tidak ikut sholat.
 *
 * Menghitung "hari sholat" tanpa kalender hari libur: hari yang tidak punya
 * satu pun baris `attendances` untuk sekolah itu dianggap libur — heuristik
 * yang PERSIS sama dengan StudentStatsBuilder::effectiveDays(), supaya angka di
 * notifikasi tidak pernah bertentangan dengan laporan yang bisa dibuka orang
 * tua dan admin.
 *
 * Seluruh data ditarik dalam beberapa query massal lalu diselesaikan di PHP;
 * memanggil ScheduleResolver per siswa per tanggal akan jadi N×M query.
 */
class PrayerAbsenceScanner
{
    /** Sejauh mana ke belakang rentetan ditelusuri. */
    private const SCAN_DAYS = 30;

    /**
     * @return Collection<int, array{student: Student, streak: int, start: string, last: string}>
     */
    public function scan(School $school, PrayerType $type, Carbon $upTo, int $threshold, bool $requirePresent): Collection
    {
        $from = $upTo->copy()->subDays(self::SCAN_DAYS)->toDateString();
        $to = $upTo->toDateString();

        $operatingDays = $this->operatingDays($school, $from, $to);

        if ($operatingDays === []) {
            return collect();
        }

        $settings = PrayerSettings::for($school, $type);
        $students = $this->candidates($school, $settings);

        if ($students->isEmpty()) {
            return collect();
        }

        $activeDaysByClassroom = $this->activeDaysByClassroom($school);
        $presence = $this->presence($school, $from, $to);
        $prayed = $this->prayed($school, $type, $from, $to);

        return $students
            ->map(function (Student $student) use ($operatingDays, $activeDaysByClassroom, $presence, $prayed, $requirePresent, $threshold) {
                $streak = $this->streakFor(
                    $student,
                    $operatingDays,
                    $activeDaysByClassroom,
                    $presence[$student->id] ?? [],
                    $prayed[$student->id] ?? [],
                    $requirePresent,
                );

                if (count($streak) < $threshold) {
                    return null;
                }

                return [
                    'student' => $student,
                    'streak' => count($streak),
                    // $streak turun (terbaru dulu), jadi awal rentetan ada di ujung.
                    'start' => $streak[count($streak) - 1],
                    'last' => $streak[0],
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Apakah siswa ini sedang punya rentetan berjalan sama sekali.
     * Dipakai untuk menutup alert yang terbuka begitu siswa sholat lagi.
     */
    public function hasBrokenStreak(
        Student $student,
        School $school,
        PrayerType $type,
        Carbon $upTo,
        bool $requirePresent,
    ): bool {
        $result = $this->scan($school, $type, $upTo, 1, $requirePresent);

        return ! $result->contains(fn (array $row) => $row['student']->id === $student->id);
    }

    /**
     * Tanggal (desc) yang punya minimal satu baris absensi — proksi kalender
     * libur.
     *
     * @return array<int, string>
     */
    private function operatingDays(School $school, string $from, string $to): array
    {
        return DB::table('attendances')
            ->where('school_id', $school->id)
            ->whereDate('attendance_date', '>=', $from)
            ->whereDate('attendance_date', '<=', $to)
            ->distinct()
            ->pluck('attendance_date')
            ->map(fn ($value) => Carbon::parse($value)->toDateString())
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Student>
     */
    private function candidates(School $school, PrayerSettings $settings): Collection
    {
        return Student::withoutGlobalScope('school')
            ->where('school_id', $school->id)
            ->where('is_active', true)
            ->whereNotNull('parent_profile_id')
            ->with(['parentProfile', 'classroom'])
            ->get()
            ->filter(fn (Student $student) => $settings->covers($student))
            ->values();
    }

    /**
     * Hari-ISO aktif per kelas, dimuat sekali. Kunci `null` = jadwal global.
     *
     * @return array<string, array<int, bool>>
     */
    private function activeDaysByClassroom(School $school): array
    {
        $map = [];

        DB::table('attendance_schedules')
            ->where('school_id', $school->id)
            ->where('is_active', true)
            ->get(['classroom_id', 'day_of_week'])
            ->each(function ($row) use (&$map) {
                $map[$row->classroom_id ?? '__global__'][(int) $row->day_of_week] = true;
            });

        return $map;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function presence(School $school, string $from, string $to): array
    {
        return $this->pivot(
            DB::table('attendances')
                ->where('school_id', $school->id)
                ->where('type', AttendanceType::CheckIn->value)
                ->whereDate('attendance_date', '>=', $from)
                ->whereDate('attendance_date', '<=', $to)
                ->get(['student_id', 'attendance_date']),
            'attendance_date',
        );
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function prayed(School $school, PrayerType $type, string $from, string $to): array
    {
        return $this->pivot(
            DB::table('prayer_attendances')
                ->where('school_id', $school->id)
                ->where('prayer_type', $type->value)
                ->whereDate('prayer_date', '>=', $from)
                ->whereDate('prayer_date', '<=', $to)
                ->get(['student_id', 'prayer_date']),
            'prayer_date',
        );
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, array<string, bool>>
     */
    private function pivot(Collection $rows, string $dateColumn): array
    {
        $map = [];

        foreach ($rows as $row) {
            $map[$row->student_id][Carbon::parse($row->{$dateColumn})->toDateString()] = true;
        }

        return $map;
    }

    /**
     * Rentetan hari sholat terakhir yang terlewat, dari terbaru ke belakang.
     *
     * - Bukan hari sholat (libur, akhir pekan) → dilewati, tidak memutus.
     * - Hari sekolah tapi siswa tidak hadir → NETRAL, juga dilewati. Ini
     *   pembunuh false-positive terpenting: tanpa ini, anak yang sakit tiga
     *   hari membuat orang tuanya menerima peringatan tidak sholat.
     * - Hadir + ada catatan sholat → rentetan putus.
     * - Hadir + tidak ada catatan → rentetan bertambah.
     *
     * @param  array<int, string>  $operatingDays  desc
     * @param  array<string, array<int, bool>>  $activeDays
     * @param  array<string, bool>  $presence
     * @param  array<string, bool>  $prayed
     * @return array<int, string> tanggal rentetan, desc
     */
    private function streakFor(
        Student $student,
        array $operatingDays,
        array $activeDays,
        array $presence,
        array $prayed,
        bool $requirePresent,
    ): array {
        $streak = [];

        foreach ($operatingDays as $date) {
            if (! $this->hasActiveSchedule($student, $date, $activeDays)) {
                continue;
            }

            if ($requirePresent && ! isset($presence[$date])) {
                continue;
            }

            if (isset($prayed[$date])) {
                break;
            }

            $streak[] = $date;
        }

        return $streak;
    }

    /**
     * Jadwal khusus kelas menang atas jadwal global, sama seperti
     * ScheduleResolver::resolve().
     *
     * @param  array<string, array<int, bool>>  $activeDays
     */
    private function hasActiveSchedule(Student $student, string $date, array $activeDays): bool
    {
        $dayOfWeek = Carbon::parse($date, SchoolTime::timezone())->dayOfWeekIso;

        if ($student->classroom_id !== null && isset($activeDays[$student->classroom_id])) {
            return isset($activeDays[$student->classroom_id][$dayOfWeek]);
        }

        return isset($activeDays['__global__'][$dayOfWeek]);
    }
}
