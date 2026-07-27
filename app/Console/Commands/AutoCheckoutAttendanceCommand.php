<?php

namespace App\Console\Commands;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\School;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;

class AutoCheckoutAttendanceCommand extends Command
{
    protected $signature = 'attendance:auto-checkout
                            {--date= : Tanggal yang diproses (Y-m-d), default hari ini}
                            {--school= : Batasi ke satu school_id}
                            {--dry-run : Tampilkan saja, jangan menulis}';

    protected $description = 'Tutup otomatis absensi yang check-in tapi tidak pernah check-out, setelah jam pulang berakhir';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::createFromFormat('Y-m-d', $this->option('date'), SchoolTime::timezone())->startOfDay()
            : SchoolTime::today();

        $isToday = $date->isSameDay(SchoolTime::today());
        $now = SchoolTime::now();
        $dryRun = (bool) $this->option('dry-run');

        $schools = School::query()
            ->when($this->option('school'), fn ($q) => $q->where('id', $this->option('school')))
            ->where('is_active', true)
            ->get();

        $closed = 0;

        foreach ($schools as $school) {
            $schedules = AttendanceSchedule::withoutGlobalScope('school')
                ->where('school_id', $school->id)
                ->where('day_of_week', $date->dayOfWeekIso)
                ->where('is_active', true)
                ->get();

            foreach ($schedules as $schedule) {
                $closesAt = $schedule->momentOn('check_out_end', $date);

                // Untuk hari berjalan, tunggu sampai jam pulang benar-benar lewat.
                if ($isToday && $now->lessThanOrEqualTo($closesAt)) {
                    continue;
                }

                $closed += $this->closeDangling($schedule, $date, $closesAt, $dryRun);
            }
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Auto check-out {$date->toDateString()}: {$closed} record ditutup.");

        return self::SUCCESS;
    }

    /**
     * Buat CHECK_OUT untuk setiap CHECK_IN yang belum berpasangan pada jadwal ini.
     */
    private function closeDangling(
        AttendanceSchedule $schedule,
        Carbon $date,
        Carbon $closesAt,
        bool $dryRun,
    ): int {
        $dangling = Attendance::withoutGlobalScope('school')
            ->where('school_id', $schedule->school_id)
            ->whereDate('attendance_date', $date->toDateString())
            ->where('type', AttendanceType::CheckIn)
            ->when(
                $schedule->classroom_id,
                fn ($q) => $q->whereHas('student', fn ($s) => $s->withoutGlobalScope('school')->where('classroom_id', $schedule->classroom_id)),
                fn ($q) => $q->whereDoesntHave('student', fn ($s) => $s->withoutGlobalScope('school')->whereIn('classroom_id', $this->classroomsWithOwnSchedule($schedule))),
            )
            ->whereNotExists(function ($query) use ($date): void {
                $query->selectRaw('1')
                    ->from('attendances as out_rows')
                    ->whereColumn('out_rows.student_id', 'attendances.student_id')
                    ->whereDate('out_rows.attendance_date', $date->toDateString())
                    ->where('out_rows.type', AttendanceType::CheckOut->value);
            })
            ->get();

        if ($dryRun) {
            return $dangling->count();
        }

        $created = 0;

        foreach ($dangling as $checkIn) {
            try {
                Attendance::create([
                    'school_id' => $checkIn->school_id,
                    'student_id' => $checkIn->student_id,
                    'attendance_date' => $checkIn->attendance_date,
                    'type' => AttendanceType::CheckOut,
                    'status' => AttendanceStatus::Hadir,
                    'recorded_at' => $closesAt,
                    'recorded_by' => null,
                    'device_id' => 'AUTO',
                    'notes' => 'Auto check-out oleh sistem (lupa scan pulang).',
                ]);
                $created++;
            } catch (UniqueConstraintViolationException) {
                // Sudah ditutup oleh proses lain — aman diabaikan.
            }
        }

        return $created;
    }

    /**
     * Kelas yang punya jadwal sendiri tidak boleh ikut ditutup oleh jadwal global.
     *
     * @return array<int, string>
     */
    private function classroomsWithOwnSchedule(AttendanceSchedule $globalSchedule): array
    {
        return AttendanceSchedule::withoutGlobalScope('school')
            ->where('school_id', $globalSchedule->school_id)
            ->where('day_of_week', $globalSchedule->day_of_week)
            ->where('is_active', true)
            ->whereNotNull('classroom_id')
            ->pluck('classroom_id')
            ->all();
    }
}
