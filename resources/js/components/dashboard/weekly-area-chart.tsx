import { Area, AreaChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

type WeeklyData = {
    day: string;
    hadir: number;
    terlambat: number;
    tidakHadir: number;
};

type WeeklyAreaChartProps = {
    data?: {
        data: WeeklyData[];
        insight: string;
    };
};

export function WeeklyAreaChart({ data }: WeeklyAreaChartProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Pola Mingguan</CardTitle>
                <CardDescription>Rata-rata 4 minggu terakhir</CardDescription>
            </CardHeader>
            <CardContent>
                {!data ? (
                    <Skeleton className="h-[400px] w-full" />
                ) : (
                    <ResponsiveContainer width="100%" height={400}>
                        <AreaChart data={data.data} margin={{ top: 5, right: 10, left: -20, bottom: 5 }}>
                            <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                            <XAxis dataKey="day" tick={{ fontSize: 12 }} className="fill-muted-foreground" />
                            <YAxis tick={{ fontSize: 12 }} className="fill-muted-foreground" />
                            <Tooltip
                                contentStyle={{
                                    backgroundColor: 'hsl(var(--card))',
                                    border: '1px solid hsl(var(--border))',
                                    borderRadius: '0.5rem',
                                    fontSize: '0.875rem',
                                }}
                            />
                            <Legend
                                verticalAlign="bottom"
                                height={36}
                                formatter={(value: string) => <span className="text-foreground text-xs">{value}</span>}
                            />
                            <Area
                                type="monotone"
                                dataKey="hadir"
                                name="Hadir"
                                stackId="1"
                                stroke="var(--color-chart-1)"
                                fill="var(--color-chart-1)"
                                fillOpacity={0.4}
                            />
                            <Area
                                type="monotone"
                                dataKey="terlambat"
                                name="Terlambat"
                                stackId="1"
                                stroke="var(--color-chart-3)"
                                fill="var(--color-chart-3)"
                                fillOpacity={0.4}
                            />
                            <Area
                                type="monotone"
                                dataKey="tidakHadir"
                                name="Tidak Hadir"
                                stackId="1"
                                stroke="var(--color-chart-5)"
                                fill="var(--color-chart-5)"
                                fillOpacity={0.4}
                            />
                        </AreaChart>
                    </ResponsiveContainer>
                )}
            </CardContent>
            {data?.insight && (
                <CardFooter>
                    <p className="text-muted-foreground text-sm">{data.insight}</p>
                </CardFooter>
            )}
        </Card>
    );
}
