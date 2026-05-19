import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

type TrendData = {
    date: string;
    hadir: number;
    terlambat: number;
};

type AttendanceTrendChartProps = {
    data?: TrendData[];
};

export function AttendanceTrendChart({ data }: AttendanceTrendChartProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Tren Kehadiran</CardTitle>
                <CardDescription>30 hari terakhir</CardDescription>
            </CardHeader>
            <CardContent>
                {!data ? (
                    <Skeleton className="h-[300px] w-full" />
                ) : (
                    <ResponsiveContainer width="100%" height={300}>
                        <LineChart data={data} margin={{ top: 5, right: 10, left: -20, bottom: 5 }}>
                            <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                            <XAxis
                                dataKey="date"
                                tick={{ fontSize: 12 }}
                                className="fill-muted-foreground"
                                interval="preserveStartEnd"
                            />
                            <YAxis tick={{ fontSize: 12 }} className="fill-muted-foreground" />
                            <Tooltip
                                contentStyle={{
                                    backgroundColor: 'hsl(var(--card))',
                                    border: '1px solid hsl(var(--border))',
                                    borderRadius: '0.5rem',
                                    fontSize: '0.875rem',
                                }}
                            />
                            <Line
                                type="monotone"
                                dataKey="hadir"
                                name="Hadir"
                                stroke="var(--color-chart-1)"
                                strokeWidth={2}
                                dot={false}
                                activeDot={{ r: 4 }}
                            />
                            <Line
                                type="monotone"
                                dataKey="terlambat"
                                name="Terlambat"
                                stroke="var(--color-chart-3)"
                                strokeWidth={2}
                                dot={false}
                                activeDot={{ r: 4 }}
                            />
                        </LineChart>
                    </ResponsiveContainer>
                )}
            </CardContent>
        </Card>
    );
}
