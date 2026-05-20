import { Head, usePage } from '@inertiajs/react';
import { AlertTriangle, GraduationCap, Percent, Users } from 'lucide-react';
import { AttendanceStatusPie } from '@/components/dashboard/attendance-status-pie';
import { AttendanceTrendChart } from '@/components/dashboard/attendance-trend-chart';
import { ClassComparisonBar } from '@/components/dashboard/class-comparison-bar';
import { LatestCheckinsTable } from '@/components/dashboard/latest-checkins-table';
import { LiveActivityFeed } from '@/components/dashboard/live-activity-feed';
import { StatCard } from '@/components/dashboard/stat-card';
import { WeeklyAreaChart } from '@/components/dashboard/weekly-area-chart';
import { dashboard } from '@/routes';

type Stats = {
    totalStudents: number;
    presentToday: number;
    lateToday: number;
    attendanceRate: number;
    totalStudentsDelta: number;
    presentTodayDelta: number;
};

type TrendItem = { date: string; hadir: number; terlambat: number };
type StatusItem = { name: string; value: number; color: string };
type ClassItem = { name: string; rate: number };
type WeeklyItem = { day: string; hadir: number; terlambat: number; tidakHadir: number };
type CheckinItem = { id: number; studentName: string; className: string | null; time: string; date: string; status: string; statusColor: string; notificationSent: boolean; initials: string };
type ActivityItem = { id: number; message: string; time: string; type: string; status: string; initials: string };

type DashboardProps = {
    stats: Stats;
    trend?: TrendItem[];
    statusDistribution?: StatusItem[];
    classComparison?: ClassItem[];
    weeklyPattern?: { data: WeeklyItem[]; insight: string };
    latestCheckins?: CheckinItem[];
    activityFeed?: ActivityItem[];
};

const today = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
}).format(new Date());

export default function Dashboard({
    stats,
    trend,
    statusDistribution,
    classComparison,
    weeklyPattern,
    latestCheckins,
    activityFeed,
}: DashboardProps) {
    const { currentSchool } = usePage().props as { currentSchool?: { name: string } | null };

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-primary text-sm font-semibold">{currentSchool?.name ?? 'Dashboard'}</p>
                        <h1 className="text-2xl font-bold tracking-tight">Selamat Datang</h1>
                    </div>
                    <p className="text-muted-foreground text-sm capitalize">{today}</p>
                </div>

                {/* Stat Cards */}
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Total Siswa"
                        value={stats.totalStudents}
                        delta={stats.totalStudentsDelta}
                        deltaLabel="vs minggu lalu"
                        icon={<GraduationCap className="size-5" />}
                        color="blue"
                    />
                    <StatCard
                        title="Hadir Hari Ini"
                        value={stats.presentToday}
                        delta={stats.presentTodayDelta}
                        deltaLabel="vs kemarin"
                        icon={<Users className="size-5" />}
                        color="green"
                    />
                    <StatCard
                        title="Terlambat"
                        value={stats.lateToday}
                        icon={<AlertTriangle className="size-5" />}
                        color="amber"
                    />
                    <StatCard
                        title="Kehadiran"
                        value={`${stats.attendanceRate}%`}
                        icon={<Percent className="size-5" />}
                        color="blue"
                    />
                </div>

                {/* Main Charts */}
                <div className="grid gap-6 lg:grid-cols-5">
                    <div className="lg:col-span-3">
                        <AttendanceTrendChart data={trend} />
                    </div>
                    <div className="lg:col-span-2">
                        <AttendanceStatusPie data={statusDistribution} />
                    </div>
                </div>

                {/* Secondary Charts */}
                <div className="grid gap-6 lg:grid-cols-2">
                    <ClassComparisonBar data={classComparison} />
                    <WeeklyAreaChart data={weeklyPattern} />
                </div>

                {/* Activity Section */}
                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <LatestCheckinsTable data={latestCheckins} />
                    </div>
                    <LiveActivityFeed data={activityFeed} />
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
    ],
};
