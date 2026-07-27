import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

export type DailyPoint = { date: string; hadir: number; terlambat: number; tidak_hadir: number };

const TOOLTIP_STYLE = {
    backgroundColor: 'var(--card)',
    border: '1px solid var(--border)',
    borderRadius: '0.5rem',
    fontSize: '0.875rem',
} as const;

export function AttendanceDailyChart({ data, range }: { data?: DailyPoint[]; range: string }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Kehadiran Harian</CardTitle>
                <CardDescription>{range}</CardDescription>
            </CardHeader>
            <CardContent>
                {!data ? (
                    <Skeleton className="h-[300px] w-full" />
                ) : data.length === 0 ? (
                    <p className="text-muted-foreground flex h-[300px] items-center justify-center text-sm">
                        Belum ada data pada rentang ini.
                    </p>
                ) : (
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
                            <Bar dataKey="hadir" name="Hadir" stackId="a" fill="var(--color-chart-2)" radius={[0, 0, 0, 0]} />
                            <Bar dataKey="terlambat" name="Terlambat" stackId="a" fill="var(--color-chart-3)" />
                            <Bar dataKey="tidak_hadir" name="Tanpa Catatan" stackId="a" fill="var(--color-chart-5)" radius={[4, 4, 0, 0]} />
                        </BarChart>
                    </ResponsiveContainer>
                )}
            </CardContent>
        </Card>
    );
}
