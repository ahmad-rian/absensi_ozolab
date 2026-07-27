import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { ChartFrame, TOOLTIP_STYLE } from '@/components/shared/chart-frame';
import type { MonthlyPoint } from '@/types/student-stats';

/**
 * Garis, bukan batang: pembaca ingin melihat arah, dan batang menyarankan
 * penjumlahan yang keliru untuk angka persentase.
 */
export function MonthlyTrendChart({ data }: { data?: MonthlyPoint[] }) {
    return (
        <ChartFrame
            title="Tren 12 Bulan"
            // Sengaja mengabaikan filter rentang: rentang default hanya satu
            // bulan, jadi tren yang menghormatinya selalu satu titik.
            description="Selalu 12 bulan terakhir, terlepas dari rentang yang dipilih"
            isLoading={!data}
            isEmpty={data?.length === 0}
        >
            <ResponsiveContainer width="100%" height={300}>
                <LineChart data={data} margin={{ top: 5, right: 10, left: -20, bottom: 5 }}>
                    <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                    <XAxis dataKey="month" tick={{ fontSize: 12 }} className="fill-muted-foreground" />
                    <YAxis domain={[0, 100]} tick={{ fontSize: 12 }} className="fill-muted-foreground" unit="%" />
                    <Tooltip contentStyle={TOOLTIP_STYLE} />
                    <Line
                        type="monotone"
                        dataKey="rate"
                        name="Kehadiran"
                        stroke="var(--color-chart-1)"
                        strokeWidth={2}
                        dot={false}
                        activeDot={{ r: 4 }}
                    />
                </LineChart>
            </ResponsiveContainer>
        </ChartFrame>
    );
}
