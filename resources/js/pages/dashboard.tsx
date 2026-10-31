import { Head, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    GraduationCap,
    Percent,
    UserX,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { AttendanceStatusPie } from '@/components/dashboard/attendance-status-pie';
import { AttendanceTrendChart } from '@/components/dashboard/attendance-trend-chart';
import { ClassComparisonBar } from '@/components/dashboard/class-comparison-bar';
import {
    DaftarTidakHadir,
    judulTidakHadir,
    nilaiTidakHadir,
} from '@/components/dashboard/daftar-tidak-hadir';
import type { TidakHadir } from '@/components/dashboard/daftar-tidak-hadir';
import { FastestStudentsCard } from '@/components/dashboard/fastest-students-card';
import type { KategoriCepat } from '@/components/dashboard/fastest-students-card';
import { LatestCheckinsTable } from '@/components/dashboard/latest-checkins-table';
import { LiveActivityFeed } from '@/components/dashboard/live-activity-feed';
import { StatCard } from '@/components/dashboard/stat-card';
import { WeeklyAreaChart } from '@/components/dashboard/weekly-area-chart';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type Periode = 'hari' | 'minggu' | 'bulan';

const PERIODE: { nilai: Periode; label: string; keterangan: string }[] = [
    { nilai: 'hari', label: 'Hari ini', keterangan: 'Hari ini' },
    { nilai: 'minggu', label: '7 hari', keterangan: '7 hari terakhir' },
    { nilai: 'bulan', label: '30 hari', keterangan: '30 hari terakhir' },
];

type Stats = {
    totalStudents: number;
    presentToday: number;
    lateToday: number;
    attendanceRate: number;
    kehadiran: { hari: number; minggu: number; bulan: number };
    totalStudentsDelta: number;
    presentTodayDelta: number;
};

type TrendItem = { date: string; hadir: number; terlambat: number };
type StatusItem = { name: string; value: number; color: string };
type ClassItem = { name: string; rate: number };
type WeeklyItem = {
    day: string;
    hadir: number;
    terlambat: number;
    tidakHadir: number;
};
type CheckinItem = {
    id: string;
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
    id: string;
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
    weeklyPattern?: { data: WeeklyItem[]; insight: string };
    latestCheckins?: CheckinItem[];
    activityFeed?: ActivityItem[];
    siswaTercepat?: KategoriCepat[];
    tidakHadir?: TidakHadir | null;
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
    siswaTercepat,
    tidakHadir,
}: DashboardProps) {
    const [periode, setPeriode] = useState<Periode>('hari');
    const { currentSchool } = usePage().props as {
        currentSchool?: { name: string } | null;
    };

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm font-semibold text-primary">
                            {currentSchool?.name ?? 'Dashboard'}
                        </p>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Selamat Datang
                        </h1>
                    </div>
                    <p className="text-sm text-muted-foreground capitalize">
                        {today}
                    </p>
                </div>

                {/* Angka utama hari ini */}
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-5">
                    <StatCard
                        title="Total Siswa"
                        value={stats.totalStudents}
                        hint="Siswa aktif"
                        icon={<GraduationCap />}
                        color="blue"
                    />
                    <StatCard
                        title="Hadir Hari Ini"
                        value={stats.presentToday}
                        delta={stats.presentTodayDelta}
                        deltaLabel="vs kemarin"
                        icon={<Users />}
                        color="green"
                    />
                    <StatCard
                        title="Terlambat"
                        value={stats.lateToday}
                        hint="Sudah termasuk hadir"
                        icon={<AlertTriangle />}
                        color="amber"
                    />
                    <StatCard
                        title={judulTidakHadir(tidakHadir)}
                        value={nilaiTidakHadir(tidakHadir)}
                        hint="Tanpa izin / sakit"
                        icon={<UserX />}
                        color="red"
                    />
                    <StatCard
                        title="Kehadiran"
                        value={`${stats.kehadiran[periode]}%`}
                        hint={`${PERIODE.find((p) => p.nilai === periode)?.keterangan} · hadir + terlambat`}
                        aksi={
                            <div
                                role="tablist"
                                aria-label="Periode kehadiran"
                                className="inline-flex rounded-md bg-muted p-0.5 text-[11px]"
                            >
                                {PERIODE.map((p) => (
                                    <button
                                        key={p.nilai}
                                        type="button"
                                        role="tab"
                                        aria-selected={periode === p.nilai}
                                        onClick={() => setPeriode(p.nilai)}
                                        className={cn(
                                            'rounded px-1.5 py-0.5 font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                            periode === p.nilai
                                                ? 'bg-background text-foreground shadow-sm'
                                                : 'text-muted-foreground hover:text-foreground',
                                        )}
                                    >
                                        {p.label}
                                    </button>
                                ))}
                            </div>
                        }
                        icon={<Percent />}
                        color="violet"
                    />
                </div>

                {/*
                    Kolom kiri: grafik dan tabel yang dibaca berurutan.
                    Kolom kanan: daftar yang perlu ditindak hari ini, selalu
                    terlihat tanpa menggulir melewati semua grafik.
                */}
                <div className="grid gap-6 xl:grid-cols-3">
                    <div className="flex min-w-0 flex-col gap-6 xl:col-span-2">
                        <AttendanceTrendChart data={trend} />
                        <div className="grid gap-6 lg:grid-cols-2">
                            <AttendanceStatusPie data={statusDistribution} />
                            <WeeklyAreaChart data={weeklyPattern} />
                        </div>
                        <LatestCheckinsTable data={latestCheckins} />
                        <ClassComparisonBar data={classComparison} />
                    </div>

                    <div className="flex min-w-0 flex-col gap-6">
                        <DaftarTidakHadir data={tidakHadir} />
                        <LiveActivityFeed data={activityFeed} />
                        <FastestStudentsCard data={siswaTercepat} />
                    </div>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
