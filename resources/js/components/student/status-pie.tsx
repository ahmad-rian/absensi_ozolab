import { Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';
import { ChartFrame, TOOLTIP_STYLE } from '@/components/shared/chart-frame';
import type { AttendanceSummary, PrayerSummary } from '@/types/student-stats';

export type PieSlice = { name: string; value: number; color: string };

const ATTENDANCE_SLICES = [
    { key: 'hadir', label: 'Hadir', color: 'var(--color-chart-2)' },
    { key: 'terlambat', label: 'Terlambat', color: 'var(--color-chart-3)' },
    { key: 'izin', label: 'Izin', color: 'var(--color-chart-1)' },
    { key: 'sakit', label: 'Sakit', color: 'var(--color-chart-4)' },
    { key: 'alpa', label: 'Alpa', color: 'var(--color-chart-5)' },
    { key: 'tanpa_keterangan', label: 'Tanpa Catatan', color: 'var(--color-muted-foreground)' },
] as const;

export function attendanceSlices(summary?: AttendanceSummary): PieSlice[] | undefined {
    if (!summary) {
        return undefined;
    }

    return ATTENDANCE_SLICES.map((slice) => ({
        name: slice.label,
        value: summary[slice.key],
        color: slice.color,
    })).filter((slice) => slice.value > 0);
}

export function prayerSlices(summary?: PrayerSummary): PieSlice[] | undefined {
    if (!summary) {
        return undefined;
    }

    return [
        { name: 'Ikut Sholat', value: summary.hadir, color: 'var(--color-chart-2)' },
        { name: 'Tidak Ikut', value: summary.tidak_hadir, color: 'var(--color-chart-5)' },
    ].filter((slice) => slice.value > 0);
}

/**
 * Digeneralkan dari versi lama yang mengunci enam status absensi sekolah —
 * itulah sebabnya tab sholat dulu tidak punya pie sama sekali.
 */
export function StatusPie({
    slices,
    title = 'Distribusi Status',
    description = 'Proporsi status pada rentang terpilih',
}: {
    slices?: PieSlice[];
    title?: string;
    description?: string;
}) {
    return (
        <ChartFrame title={title} description={description} isLoading={!slices} isEmpty={slices?.length === 0}>
            <ResponsiveContainer width="100%" height={300}>
                <PieChart>
                    <Pie data={slices} dataKey="value" nameKey="name" innerRadius={60} outerRadius={100} paddingAngle={3}>
                        {(slices ?? []).map((slice) => (
                            <Cell key={slice.name} fill={slice.color} />
                        ))}
                    </Pie>
                    <Tooltip contentStyle={TOOLTIP_STYLE} />
                    <Legend wrapperStyle={{ fontSize: '0.75rem' }} />
                </PieChart>
            </ResponsiveContainer>
        </ChartFrame>
    );
}
