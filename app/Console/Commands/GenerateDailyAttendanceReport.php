<?php

namespace App\Console\Commands;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateDailyAttendanceReport extends Command
{
    protected $signature = 'attendance:report {--date= : Date in Y-m-d format (defaults to today)}';

    protected $description = 'Generate a daily attendance report summary';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::today();

        $this->info("Attendance Report for {$date->format('d F Y')}");
        $this->newLine();

        $totalStudents = Student::where('is_active', true)->count();

        $stats = Attendance::where('attendance_date', $date)
            ->where('type', AttendanceType::CheckIn)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $hadir = $stats[AttendanceStatus::Hadir->value] ?? 0;
        $terlambat = $stats[AttendanceStatus::Terlambat->value] ?? 0;
        $izin = $stats[AttendanceStatus::Izin->value] ?? 0;
        $sakit = $stats[AttendanceStatus::Sakit->value] ?? 0;
        $alpa = $stats[AttendanceStatus::Alpa->value] ?? 0;
        $recorded = $hadir + $terlambat + $izin + $sakit + $alpa;
        $notRecorded = $totalStudents - $recorded;

        $this->table(
            ['Status', 'Jumlah', '%'],
            [
                ['Hadir', $hadir, $this->pct($hadir, $totalStudents)],
                ['Terlambat', $terlambat, $this->pct($terlambat, $totalStudents)],
                ['Izin', $izin, $this->pct($izin, $totalStudents)],
                ['Sakit', $sakit, $this->pct($sakit, $totalStudents)],
                ['Alpa', $alpa, $this->pct($alpa, $totalStudents)],
                ['Belum Tercatat', $notRecorded, $this->pct($notRecorded, $totalStudents)],
                ['─────────', '───', '───'],
                ['Total Siswa', $totalStudents, '100%'],
            ],
        );

        return self::SUCCESS;
    }

    private function pct(int $value, int $total): string
    {
        if ($total === 0) {
            return '0%';
        }

        return round(($value / $total) * 100, 1).'%';
    }
}
