import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { ChartFrame, TOOLTIP_STYLE } from '@/components/shared/chart-frame';
import type { DailyPoint } from '@/types/student-stats';

export type DailySeries = {
    key: string;
    name: string;
    color: string;
    /** Radius hanya di seri paling atas tumpukan. */
    radius?: [number, number, number, number];
};

// Warnanya sengaja sama dengan irisan pie di status-pie.tsx: satu status harus
// berwarna sama di mana pun ia muncul.
export const ATTENDANCE_SERIES: DailySeries[] = [
    { key: 'hadir', name: 'Hadir', color: 'var(--color-chart-2)' },
    { key: 'terlambat', name: 'Terlambat', color: 'var(--color-chart-3)' },
    { key: 'izin', name: 'Izin', color: 'var(--color-chart-1)' },
    { key: 'sakit', name: 'Sakit', color: 'var(--color-chart-4)' },
    { key: 'alpa', name: 'Alpa', color: 'var(--color-chart-5)' },
    { key: 'tanpa_keterangan', name: 'Tanpa Catatan', color: 'var(--color-muted-foreground)', radius: [4, 4, 0, 0] },
];

export const PRAYER_SERIES: DailySeries[] = [
    { key: 'hadir', name: 'Ikut Sholat', color: 'var(--color-chart-2)' },
    { key: 'tidak_hadir', name: 'Tidak Ikut', color: 'var(--color-chart-5)', radius: [4, 4, 0, 0] },
];

/**
 * Menggantikan attendance-daily-chart dan prayer-daily-chart yang dulu terpisah
 * padahal hanya berbeda judul dan daftar seri — dengan tiga jenis absen,
 * perbedaan kecil di antara salinannya sudah pasti menjadi permanen.
 */
export function DailyBarChart({
    data,
    series,
    title,
    description,
}: {
    data?: DailyPoint[];
    series: DailySeries[];
    title: string;
    description: string;
}) {
    return (
        <ChartFrame title={title} description={description} isLoading={!data} isEmpty={data?.length === 0}>
            <ResponsiveContainer width="100%" height={300}>
                <BarChart data={data} margin={{ top: 5, right: 10, left: -25, bottom: 5 }}>
                    <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                    <XAxis
                        dataKey="date"
                        tick={{ fontSize: 12 }}
                        className="fill-muted-foreground"
                        interval="preserveStartEnd"
                    />
                    <YAxis allowDecimals={false} tick={{ fontSize: 12 }} className="fill-muted-foreground" />
                    <Tooltip contentStyle={TOOLTIP_STYLE} />
                    <Legend wrapperStyle={{ fontSize: '0.75rem' }} />
                    {series.map((entry) => (
                        <Bar
                            key={entry.key}
                            dataKey={entry.key}
                            name={entry.name}
                            stackId="a"
                            fill={entry.color}
                            radius={entry.radius}
                        />
                    ))}
                </BarChart>
            </ResponsiveContainer>
        </ChartFrame>
    );
}
