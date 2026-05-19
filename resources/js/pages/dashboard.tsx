import { Head } from '@inertiajs/react';
import { Activity, AlertTriangle, GraduationCap, Percent, Users } from 'lucide-react';
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

type TrendItem = {
    date: string;
    hadir: number;
    terlambat: number;
};

type StatusItem = {
    name: string;
    value: number;
    color: string;
};

type ClassItem = {
    name: string;
    rate: number;
};

type WeeklyItem = {
    day: string;
    hadir: number;
    terlambat: number;
    tidakHadir: number;
};

type CheckinItem = {
    id: number;
    studentName: string;
    className: string | null;
    time: string;
    date: string;
    status: string;
    statusColor: string;
    notificationSent: boolean;
    initials: string;
};

type ActivityItem = {
    id: number;
    message: string;
    time: string;
    type: string;
    status: string;
    initials: string;
};

type DashboardProps = {
    stats: Stats;
    trend?: TrendItem[];
    statusDistribution?: StatusItem[];
    classComparison?: ClassItem[];
    weeklyPattern?: {
        data: WeeklyItem[];
        insight: string;
    };
    latestCheckins?: CheckinItem[];
    activityFeed?: ActivityItem[];
    notificationStats?: {
        sent: number;
        failed: number;
    };
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
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                {/* Page Header */}
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Dashboard</h1>
                    <p className="text-muted-foreground text-sm capitalize">{today}</p>
                </div>

                {/* Stat Cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Total Siswa"
                        value={stats.totalStudents}
                        delta={stats.totalStudentsDelta}
                        deltaLabel="vs minggu lalu"
                        icon={<GraduationCap className="size-4" />}
                    />
                    <StatCard
                        title="Hadir Hari Ini"
                        value={stats.presentToday}
                        delta={stats.presentTodayDelta}
                        deltaLabel="vs kemarin"
                        icon={<Users className="size-4" />}
                    />
                    <StatCard
                        title="Terlambat Hari Ini"
                        value={stats.lateToday}
                        icon={<AlertTriangle className="size-4" />}
                        variant={stats.lateToday > 10 ? 'warning' : 'default'}
                    />
                    <StatCard
                        title="Tingkat Kehadiran"
                        value={`${stats.attendanceRate}%`}
                        icon={<Percent className="size-4" />}
                    />
                </div>

                {/* Main Charts */}
                <div className="grid gap-6 lg:grid-cols-2">
                    <AttendanceTrendChart data={trend} />
                    <AttendanceStatusPie data={statusDistribution} />
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
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
