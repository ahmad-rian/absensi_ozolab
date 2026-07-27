import { Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

export type AttendanceSummary = {
    hadir: number;
    terlambat: number;
    izin: number;
    sakit: number;
    alpa: number;
    tanpa_keterangan: number;
    effective_days: number;
    recorded_days: number;
    rate: number;
};

const SLICES = [
    { key: 'hadir', label: 'Hadir', color: 'var(--color-chart-2)' },
    { key: 'terlambat', label: 'Terlambat', color: 'var(--color-chart-3)' },
    { key: 'izin', label: 'Izin', color: 'var(--color-chart-1)' },
    { key: 'sakit', label: 'Sakit', color: 'var(--color-chart-4)' },
    { key: 'alpa', label: 'Alpa', color: 'var(--color-chart-5)' },
    { key: 'tanpa_keterangan', label: 'Tanpa Catatan', color: 'var(--color-muted-foreground)' },
] as const;

export function StatusPie({ summary }: { summary?: AttendanceSummary }) {
    const data = summary
        ? SLICES.map((slice) => ({ name: slice.label, value: summary[slice.key], color: slice.color })).filter(
              (slice) => slice.value > 0,
          )
        : [];

    return (
        <Card>
            <CardHeader>
                <CardTitle>Distribusi Status</CardTitle>
                <CardDescription>Proporsi status kehadiran pada rentang terpilih</CardDescription>
            </CardHeader>
            <CardContent>
                {!summary ? (
                    <Skeleton className="h-[300px] w-full" />
                ) : data.length === 0 ? (
                    <p className="text-muted-foreground flex h-[300px] items-center justify-center text-sm">
                        Belum ada data pada rentang ini.
                    </p>
                ) : (
                    <ResponsiveContainer width="100%" height={300}>
                        <PieChart>
                            <Pie data={data} dataKey="value" nameKey="name" innerRadius={60} outerRadius={100} paddingAngle={3}>
                                {data.map((slice) => (
                                    <Cell key={slice.name} fill={slice.color} />
                                ))}
                            </Pie>
                            <Tooltip
                                contentStyle={{
                                    backgroundColor: 'var(--card)',
                                    border: '1px solid var(--border)',
                                    borderRadius: '0.5rem',
                                    fontSize: '0.875rem',
                                }}
                            />
                            <Legend wrapperStyle={{ fontSize: '0.75rem' }} />
                        </PieChart>
                    </ResponsiveContainer>
                )}
            </CardContent>
        </Card>
    );
}
