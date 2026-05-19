import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

type ClassData = {
    name: string;
    rate: number;
};

type ClassComparisonBarProps = {
    data?: ClassData[];
};

export function ClassComparisonBar({ data }: ClassComparisonBarProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Perbandingan Antar Kelas</CardTitle>
                <CardDescription>% kehadiran 30 hari terakhir</CardDescription>
            </CardHeader>
            <CardContent>
                {!data ? (
                    <Skeleton className="h-[400px] w-full" />
                ) : (
                    <ResponsiveContainer width="100%" height={400}>
                        <BarChart data={data} layout="vertical" margin={{ top: 5, right: 20, left: 0, bottom: 5 }}>
                            <CartesianGrid strokeDasharray="3 3" className="stroke-border" horizontal={false} />
                            <XAxis
                                type="number"
                                domain={[0, 100]}
                                tick={{ fontSize: 12 }}
                                className="fill-muted-foreground"
                                tickFormatter={(v: number) => `${v}%`}
                            />
                            <YAxis type="category" dataKey="name" tick={{ fontSize: 12 }} className="fill-muted-foreground" width={40} />
                            <Tooltip
                                contentStyle={{
                                    backgroundColor: 'hsl(var(--card))',
                                    border: '1px solid hsl(var(--border))',
                                    borderRadius: '0.5rem',
                                    fontSize: '0.875rem',
                                }}
                                formatter={(value: number) => [`${value}%`, 'Kehadiran']}
                            />
                            <Bar dataKey="rate" fill="var(--color-chart-1)" radius={[0, 4, 4, 0]} barSize={16} />
                        </BarChart>
                    </ResponsiveContainer>
                )}
            </CardContent>
        </Card>
    );
}
