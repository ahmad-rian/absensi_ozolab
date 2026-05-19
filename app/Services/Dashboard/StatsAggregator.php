<?php

namespace App\Services\Dashboard;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StatsAggregator
{
    /**
     * @return array{totalStudents: int, presentToday: int, lateToday: int, attendanceRate: float}
     */
    public function getOverview(?Carbon $date = null): array
    {
        $date ??= Carbon::today();

        $totalStudents = Student::where('is_active', true)->count();

        $presentToday = Attendance::where('attendance_date', $date)
            ->where('type', AttendanceType::CheckIn)
            ->where('status', AttendanceStatus::Hadir)
            ->count();

        $lateToday = Attendance::where('attendance_date', $date)
            ->where('type', AttendanceType::CheckIn)
            ->where('status', AttendanceStatus::Terlambat)
            ->count();

        $thirtyDaysAgo = $date->copy()->subDays(30);
        $rateData = Attendance::where('type', AttendanceType::CheckIn)
            ->whereBetween('attendance_date', [$thirtyDaysAgo, $date])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as hadir', [AttendanceStatus::Hadir->value])
            ->selectRaw('COUNT(*) as total')
            ->first();

        $attendanceRate = $rateData->total > 0
            ? round(($rateData->hadir / $rateData->total) * 100, 1)
            : 0;

        return [
            'totalStudents' => $totalStudents,
            'presentToday' => $presentToday,
            'lateToday' => $lateToday,
            'attendanceRate' => $attendanceRate,
        ];
    }

    /**
     * @return array<string, array{classroom_id: int, name: string, rate: float}>
     */
    public function getClassroomRanking(?Carbon $date = null, int $days = 30): array
    {
        $date ??= Carbon::today();
        $startDate = $date->copy()->subDays($days);

        return DB::table('attendances')
            ->join('students', 'attendances.student_id', '=', 'students.id')
            ->join('classrooms', 'students.classroom_id', '=', 'classrooms.id')
            ->where('attendances.type', AttendanceType::CheckIn->value)
            ->whereBetween('attendances.attendance_date', [$startDate, $date])
            ->groupBy('classrooms.id', 'classrooms.name')
            ->selectRaw('classrooms.id as classroom_id, classrooms.name')
            ->selectRaw('ROUND(COUNT(CASE WHEN attendances.status = ? THEN 1 END) * 100.0 / COUNT(*), 1) as rate', [AttendanceStatus::Hadir->value])
            ->orderByDesc('rate')
            ->get()
            ->keyBy('classroom_id')
            ->toArray();
    }
}
