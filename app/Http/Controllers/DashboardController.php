<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\NotificationLog;
use App\Models\Student;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $today = Carbon::today();
        $totalActiveStudents = Student::forSchool()->where('is_active', true)->count();

        return Inertia::render('dashboard', [
            'stats' => $this->getStatCards($today, $totalActiveStudents),
            'trend' => Inertia::defer(fn () => $this->getAttendanceTrend($today)),
            'statusDistribution' => Inertia::defer(fn () => $this->getStatusDistribution($today)),
            'classComparison' => Inertia::defer(fn () => $this->getClassComparison($today)),
            'weeklyPattern' => Inertia::defer(fn () => $this->getWeeklyPattern($today)),
            'latestCheckins' => Inertia::defer(fn () => $this->getLatestCheckins()),
            'activityFeed' => Inertia::defer(fn () => $this->getActivityFeed()),
            'notificationStats' => Inertia::defer(fn () => $this->getNotificationStats($today)),
        ]);
    }

    /**
     * @return array{totalStudents: int, presentToday: int, lateToday: int, attendanceRate: float, totalStudentsDelta: float, presentTodayDelta: float}
     */
    private function getStatCards(Carbon $today, int $totalActiveStudents): array
    {
        $schoolId = auth()->user()->school_id;

        $presentToday = Attendance::whereHas('student', fn ($q) => $q->where('school_id', $schoolId))
            ->where('attendance_date', $today)
            ->where('type', AttendanceType::CheckIn)
            ->where('status', AttendanceStatus::Hadir)
            ->count();

        $lateToday = Attendance::whereHas('student', fn ($q) => $q->where('school_id', $schoolId))
            ->where('attendance_date', $today)
            ->where('type', AttendanceType::CheckIn)
            ->where('status', AttendanceStatus::Terlambat)
            ->count();

        $yesterday = $today->copy()->subDay();
        if ($yesterday->isWeekend()) {
            $yesterday = $today->copy()->previous(Carbon::FRIDAY);
        }

        $presentYesterday = Attendance::whereHas('student', fn ($q) => $q->where('school_id', $schoolId))
            ->where('attendance_date', $yesterday)
            ->where('type', AttendanceType::CheckIn)
            ->where('status', AttendanceStatus::Hadir)
            ->count();

        // Attendance rate for the last 30 days
        $thirtyDaysAgo = $today->copy()->subDays(30);
        $attendanceRateData = Attendance::whereHas('student', fn ($q) => $q->where('school_id', $schoolId))
            ->where('type', AttendanceType::CheckIn)
            ->where('attendance_date', '>=', $thirtyDaysAgo)
            ->where('attendance_date', '<=', $today)
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as hadir', [AttendanceStatus::Hadir->value])
            ->selectRaw('COUNT(*) as total')
            ->first();

        $attendanceRate = $attendanceRateData->total > 0
            ? round(($attendanceRateData->hadir / $attendanceRateData->total) * 100, 1)
            : 0;

        $presentTodayDelta = $presentYesterday > 0
            ? round((($presentToday - $presentYesterday) / $presentYesterday) * 100, 1)
            : 0;

        return [
            'totalStudents' => $totalActiveStudents,
            'presentToday' => $presentToday,
            'lateToday' => $lateToday,
            'attendanceRate' => $attendanceRate,
            'totalStudentsDelta' => 0,
            'presentTodayDelta' => $presentTodayDelta,
        ];
    }

    /**
     * @return list<array{date: string, hadir: int, terlambat: int}>
     */
    private function getAttendanceTrend(Carbon $today): array
    {
        $startDate = $today->copy()->subDays(30);
        $schoolId = auth()->user()->school_id;

        $data = Attendance::whereHas('student', fn ($q) => $q->where('school_id', $schoolId))
            ->where('type', AttendanceType::CheckIn)
            ->where('attendance_date', '>=', $startDate)
            ->where('attendance_date', '<=', $today)
            ->groupBy('attendance_date')
            ->select(
                'attendance_date',
                DB::raw('COUNT(CASE WHEN status = \''.AttendanceStatus::Hadir->value.'\' THEN 1 END) as hadir'),
                DB::raw('COUNT(CASE WHEN status = \''.AttendanceStatus::Terlambat->value.'\' THEN 1 END) as terlambat'),
            )
            ->orderBy('attendance_date')
            ->get();

        $result = [];
        $period = CarbonPeriod::create($startDate, $today);

        foreach ($period as $date) {
            if ($date->isWeekend()) {
                continue;
            }

            $dateStr = $date->format('Y-m-d');
            $row = $data->firstWhere('attendance_date', $dateStr);

            $result[] = [
                'date' => $date->format('d M'),
                'hadir' => $row ? (int) $row->hadir : 0,
                'terlambat' => $row ? (int) $row->terlambat : 0,
            ];
        }

        return $result;
    }

    /**
     * @return list<array{name: string, value: int, color: string}>
     */
    private function getStatusDistribution(Carbon $today): array
    {
        $schoolId = auth()->user()->school_id;

        $data = Attendance::whereHas('student', fn ($q) => $q->where('school_id', $schoolId))
            ->where('type', AttendanceType::CheckIn)
            ->where('attendance_date', $today)
            ->groupBy('status')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->get()
            ->keyBy('status');

        $colors = [
            AttendanceStatus::Hadir->value => 'var(--chart-1)',
            AttendanceStatus::Terlambat->value => 'var(--chart-3)',
            AttendanceStatus::Izin->value => 'var(--chart-2)',
            AttendanceStatus::Sakit->value => 'var(--chart-4)',
            AttendanceStatus::Alpa->value => 'var(--chart-5)',
        ];

        $result = [];
        foreach (AttendanceStatus::cases() as $status) {
            $result[] = [
                'name' => $status->label(),
                'value' => isset($data[$status->value]) ? (int) $data[$status->value]->total : 0,
                'color' => $colors[$status->value],
            ];
        }

        return $result;
    }

    /**
     * @return list<array{name: string, rate: float}>
     */
    private function getClassComparison(Carbon $today): array
    {
        $thirtyDaysAgo = $today->copy()->subDays(30);
        $schoolId = auth()->user()->school_id;

        $classrooms = Classroom::forSchool()
            ->with(['students' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        $attendanceData = Attendance::where('type', AttendanceType::CheckIn)
            ->where('attendance_date', '>=', $thirtyDaysAgo)
            ->where('attendance_date', '<=', $today)
            ->join('students', 'attendances.student_id', '=', 'students.id')
            ->where('students.school_id', $schoolId)
            ->groupBy('students.classroom_id')
            ->select(
                'students.classroom_id',
                DB::raw('COUNT(CASE WHEN attendances.status = \''.AttendanceStatus::Hadir->value.'\' THEN 1 END) as hadir'),
                DB::raw('COUNT(*) as total'),
            )
            ->get()
            ->keyBy('classroom_id');

        $result = [];
        foreach ($classrooms as $classroom) {
            $data = $attendanceData[$classroom->id] ?? null;
            $rate = $data && $data->total > 0
                ? round(($data->hadir / $data->total) * 100, 1)
                : 0;

            $result[] = [
                'name' => $classroom->name,
                'rate' => $rate,
            ];
        }

        return $result;
    }

    /**
     * @return array{data: list<array{day: string, hadir: int, terlambat: int, tidakHadir: int}>, insight: string}
     */
    private function getWeeklyPattern(Carbon $today): array
    {
        $fourWeeksAgo = $today->copy()->subWeeks(4);
        $dayNames = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
        $schoolId = auth()->user()->school_id;

        $data = Attendance::whereHas('student', fn ($q) => $q->where('school_id', $schoolId))
            ->where('type', AttendanceType::CheckIn)
            ->where('attendance_date', '>=', $fourWeeksAgo)
            ->where('attendance_date', '<=', $today)
            ->select(
                DB::raw('DAYOFWEEK(attendance_date) as dow'),
                DB::raw('COUNT(CASE WHEN status = \''.AttendanceStatus::Hadir->value.'\' THEN 1 END) as hadir'),
                DB::raw('COUNT(CASE WHEN status = \''.AttendanceStatus::Terlambat->value.'\' THEN 1 END) as terlambat'),
                DB::raw('COUNT(CASE WHEN status IN (\''.AttendanceStatus::Alpa->value.'\',\''.AttendanceStatus::Izin->value.'\',\''.AttendanceStatus::Sakit->value.'\') THEN 1 END) as tidak_hadir'),
            )
            ->groupBy(DB::raw('DAYOFWEEK(attendance_date)'))
            ->get()
            ->keyBy('dow');

        $result = [];
        $maxLate = 0;
        $maxLateDay = 'Senin';

        // MySQL DAYOFWEEK: 1=Sunday, 2=Monday, ...
        foreach ([2, 3, 4, 5, 6] as $i => $dow) {
            $row = $data[$dow] ?? null;
            $hadir = $row ? (int) $row->hadir : 0;
            $terlambat = $row ? (int) $row->terlambat : 0;
            $tidakHadir = $row ? (int) $row->tidak_hadir : 0;

            if ($terlambat > $maxLate) {
                $maxLate = $terlambat;
                $maxLateDay = $dayNames[$i];
            }

            $result[] = [
                'day' => $dayNames[$i],
                'hadir' => $hadir,
                'terlambat' => $terlambat,
                'tidakHadir' => $tidakHadir,
            ];
        }

        return [
            'data' => $result,
            'insight' => "Hari paling sering terlambat: {$maxLateDay}",
        ];
    }

    /**
     * @return list<array{id: int, studentName: string, className: string|null, time: string, status: string, statusColor: string, notificationSent: bool}>
     */
    private function getLatestCheckins(): array
    {
        $schoolId = auth()->user()->school_id;

        $checkins = Attendance::with(['student.classroom', 'student.parentProfile'])
            ->whereHas('student', fn ($q) => $q->where('school_id', $schoolId))
            ->where('type', AttendanceType::CheckIn)
            ->orderByDesc('recorded_at')
            ->limit(10)
            ->get();

        return $checkins->map(fn (Attendance $a) => [
            'id' => $a->id,
            'studentName' => $a->student->full_name,
            'className' => $a->student->classroom?->name,
            'time' => $a->recorded_at?->format('H:i'),
            'date' => $a->attendance_date->format('d M Y'),
            'status' => $a->status->label(),
            'statusColor' => $a->status->color(),
            'notificationSent' => $a->notificationLogs()->where('status', 'SENT')->exists(),
            'initials' => $this->getInitials($a->student->full_name),
        ])->toArray();
    }

    /**
     * @return list<array{id: int, message: string, time: string}>
     */
    private function getActivityFeed(): array
    {
        $schoolId = auth()->user()->school_id;

        $activities = Attendance::with('student')
            ->whereHas('student', fn ($q) => $q->where('school_id', $schoolId))
            ->orderByDesc('recorded_at')
            ->limit(20)
            ->get();

        return $activities->map(fn (Attendance $a) => [
            'id' => $a->id,
            'message' => "{$a->student->full_name} {$a->type->label()} - {$a->status->label()}",
            'time' => $a->recorded_at?->diffForHumans(),
            'type' => $a->type->value,
            'status' => $a->status->value,
            'initials' => $this->getInitials($a->student->full_name),
        ])->toArray();
    }

    /**
     * @return array{sent: int, failed: int}
     */
    private function getNotificationStats(Carbon $today): array
    {
        $schoolId = auth()->user()->school_id;

        $sent = NotificationLog::whereHas('student', fn ($q) => $q->where('school_id', $schoolId))
            ->where('status', 'SENT')
            ->whereDate('sent_at', $today)
            ->count();

        $failed = NotificationLog::whereHas('student', fn ($q) => $q->where('school_id', $schoolId))
            ->where('status', 'FAILED')
            ->whereDate('created_at', $today)
            ->count();

        return [
            'sent' => $sent,
            'failed' => $failed,
        ];
    }

    private function getInitials(string $name): string
    {
        $words = explode(' ', $name);
        $initials = '';
        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return $initials;
    }
}
