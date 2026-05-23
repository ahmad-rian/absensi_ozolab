<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\Student;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AttendanceHistorySeeder extends Seeder
{
    /** @var list<string> */
    private array $holidays = [
        '2026-01-01', // Tahun Baru
        '2026-02-18', // Isra Miraj
        '2026-03-22', // Nyepi
        '2026-03-29', // Hari Raya Idul Fitri
        '2026-03-30', // Hari Raya Idul Fitri
        '2026-04-02', // Wafat Isa Almasih
        '2026-05-01', // Hari Buruh
        '2026-05-13', // Kenaikan Isa Almasih
        '2026-05-16', // Hari Raya Waisak
    ];

    public function run(): void
    {
        $students = Student::where('is_active', true)->get();
        $startDate = Carbon::now()->subDays(90);
        $endDate = Carbon::now()->subDay();

        $period = CarbonPeriod::create($startDate, $endDate);
        $schoolDays = [];

        foreach ($period as $date) {
            if ($date->isWeekend()) {
                continue;
            }
            if (in_array($date->format('Y-m-d'), $this->holidays)) {
                continue;
            }
            $schoolDays[] = $date->copy();
        }

        $records = [];
        $batchSize = 500;

        foreach ($students as $student) {
            foreach ($schoolDays as $date) {
                $isMonday = $date->dayOfWeek === Carbon::MONDAY;

                // Distribution: 88% HADIR, 6% TERLAMBAT, 3% IZIN, 2% SAKIT, 1% ALPA
                $rand = fake()->numberBetween(1, 100);
                $lateThreshold = $isMonday ? 12 : 6; // Higher late rate on Monday

                if ($rand <= (88 - ($isMonday ? 6 : 0))) {
                    $status = AttendanceStatus::Hadir;
                    $checkInMinute = fake()->numberBetween(0, 14);
                    $checkInSecond = fake()->numberBetween(0, 59);
                    $checkInTime = "07:{$this->pad($checkInMinute)}:{$this->pad($checkInSecond)}";
                } elseif ($rand <= 88 + $lateThreshold - ($isMonday ? 0 : 0)) {
                    $status = AttendanceStatus::Terlambat;
                    $checkInMinute = fake()->numberBetween(16, 45);
                    $checkInSecond = fake()->numberBetween(0, 59);
                    $checkInTime = "07:{$this->pad($checkInMinute)}:{$this->pad($checkInSecond)}";
                } elseif ($rand <= 94) {
                    $status = AttendanceStatus::Izin;
                    $checkInTime = null;
                } elseif ($rand <= 98) {
                    $status = AttendanceStatus::Sakit;
                    $checkInTime = null;
                } else {
                    $status = AttendanceStatus::Alpa;
                    $checkInTime = null;
                }

                $dateStr = $date->format('Y-m-d');

                // CHECK_IN record
                $records[] = [
                    'id' => (string) Str::ulid(),
                    'school_id' => $student->school_id,
                    'student_id' => $student->id,
                    'attendance_date' => $dateStr,
                    'type' => AttendanceType::CheckIn->value,
                    'status' => $status->value,
                    'recorded_at' => $checkInTime ? "{$dateStr} {$checkInTime}" : "{$dateStr} 07:00:00",
                    'recorded_by' => null,
                    'device_id' => 'SCANNER-01',
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // CHECK_OUT only if student was present
                if (in_array($status, [AttendanceStatus::Hadir, AttendanceStatus::Terlambat])) {
                    $checkOutMinute = fake()->numberBetween(30, 50);
                    $checkOutSecond = fake()->numberBetween(0, 59);
                    $records[] = [
                        'id' => (string) Str::ulid(),
                        'school_id' => $student->school_id,
                        'student_id' => $student->id,
                        'attendance_date' => $dateStr,
                        'type' => AttendanceType::CheckOut->value,
                        'status' => AttendanceStatus::Hadir->value,
                        'recorded_at' => "{$dateStr} 14:{$this->pad($checkOutMinute)}:{$this->pad($checkOutSecond)}",
                        'recorded_by' => null,
                        'device_id' => 'SCANNER-01',
                        'notes' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (count($records) >= $batchSize) {
                    Attendance::insert($records);
                    $records = [];
                }
            }
        }

        if (count($records) > 0) {
            Attendance::insert($records);
        }
    }

    private function pad(int $number): string
    {
        return str_pad((string) $number, 2, '0', STR_PAD_LEFT);
    }
}
