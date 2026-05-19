<?php

namespace App\Console\Commands;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\Classroom;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncAttendanceStatsCommand extends Command
{
    protected $signature = 'attendance:sync-stats {--days=30 : Number of days to calculate stats for}';

    protected $description = 'Sync and cache attendance statistics for dashboard';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $endDate = Carbon::today();
        $startDate = $endDate->copy()->subDays($days);

        $this->info("Syncing attendance stats for last {$days} days...");

        // Cache overall stats
        $overallRate = Attendance::where('type', AttendanceType::CheckIn)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0) as rate', [AttendanceStatus::Hadir->value])
            ->value('rate') ?? 0;

        Cache::put('stats.overall_attendance_rate', round((float) $overallRate, 1), now()->addHours(1));

        // Cache per-classroom stats
        $classrooms = Classroom::all();
        foreach ($classrooms as $classroom) {
            $rate = Attendance::where('type', AttendanceType::CheckIn)
                ->whereBetween('attendance_date', [$startDate, $endDate])
                ->whereHas('student', fn ($q) => $q->where('classroom_id', $classroom->id))
                ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0) as rate', [AttendanceStatus::Hadir->value])
                ->value('rate') ?? 0;

            Cache::put("stats.classroom.{$classroom->id}.rate", round((float) $rate, 1), now()->addHours(1));
        }

        $this->info('Stats synced successfully. Overall rate: '.Cache::get('stats.overall_attendance_rate').'%');

        return self::SUCCESS;
    }
}
