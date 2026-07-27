import { Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';
import { TOOLTIP_STYLE } from '@/components/shared/chart-frame';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

type StatusData = {
    name: string;
    value: number;
    color: string;
};

type AttendanceStatusPieProps = {
    data?: StatusData[];
};

export function AttendanceStatusPie({ data }: AttendanceStatusPieProps) {
    const hasData = data && data.some((d) => d.value > 0);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Distribusi Status</CardTitle>
                <CardDescription>Hari ini</CardDescription>
            </CardHeader>
            <CardContent>
                {!data ? (
                    <Skeleton className="h-[300px] w-full" />
                ) : !hasData ? (
                    <div className="text-muted-foreground flex h-[300px] items-center justify-center text-sm">
                        Belum ada data absensi hari ini
                    </div>
                ) : (
                    <ResponsiveContainer width="100%" height={300}>
                        <PieChart>
                            <Pie
                                data={data.filter((d) => d.value > 0)}
                                cx="50%"
                                cy="50%"
                                innerRadius={60}
                                outerRadius={100}
                                paddingAngle={3}
                                dataKey="value"
                                nameKey="name"
                            >
                                {data
                                    .filter((d) => d.value > 0)
                                    .map((entry, index) => (
                                        <Cell key={`cell-${index}`} fill={entry.color} />
                                    ))}
                            </Pie>
                            <Tooltip
                                contentStyle={TOOLTIP_STYLE}
                            />
                            <Legend
                                verticalAlign="bottom"
                                height={36}
                                formatter={(value: string) => <span className="text-foreground text-xs">{value}</span>}
                            />
                        </PieChart>
                    </ResponsiveContainer>
                )}
            </CardContent>
        </Card>
    );
}
