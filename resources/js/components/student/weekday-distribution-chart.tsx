import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { ChartFrame, TOOLTIP_STYLE } from '@/components/shared/chart-frame';
import type { WeekdayBlock } from '@/types/student-stats';

/**
 * Batang horizontal, bukan vertikal: label harinya panjang dan kategorinya
 * hanya lima, jadi bentuk ini terbaca langsung sebagai peringkat "hari terlemah".
 */
export function WeekdayDistributionChart({ data, title }: { data?: WeekdayBlock; title: string }) {
    const worst = data?.worst_day;

    return (
        <ChartFrame
            title={title}
            description={worst ? `Keterlambatan tertinggi jatuh di hari ${worst}` : 'Sebaran per hari sekolah'}
            isLoading={!data}
            isEmpty={data?.series.length === 0}
        >
            <ResponsiveContainer width="100%" height={300}>
                <BarChart
                    layout="vertical"
                    data={data?.series}
                    margin={{ top: 5, right: 16, left: 8, bottom: 5 }}
                >
                    <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                    <XAxis type="number" allowDecimals={false} tick={{ fontSize: 12 }} className="fill-muted-foreground" />
                    <YAxis
                        type="category"
                        dataKey="weekday"
                        width={64}
                        tick={{ fontSize: 12 }}
                        className="fill-muted-foreground"
                    />
                    <Tooltip contentStyle={TOOLTIP_STYLE} />
                    <Legend wrapperStyle={{ fontSize: '0.75rem' }} />
                    <Bar dataKey="hadir" name="Hadir" stackId="a" fill="var(--color-chart-2)" />
                    <Bar dataKey="terlambat" name="Terlambat" stackId="a" fill="var(--color-chart-3)" radius={[0, 4, 4, 0]} />
                </BarChart>
            </ResponsiveContainer>
        </ChartFrame>
    );
}
