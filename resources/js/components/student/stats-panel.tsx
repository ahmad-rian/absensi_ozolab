import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';
import { AttendanceHeatmap } from '@/components/student/attendance-heatmap';
import { DailyBarChart, type DailySeries } from '@/components/student/daily-bar-chart';
import { HistoryTable } from '@/components/student/history-table';
import { MonthlyTrendChart } from '@/components/student/monthly-trend-chart';
import { PeerComparisonCard } from '@/components/student/peer-comparison-card';
import { RangeBar, type ExportLink } from '@/components/student/range-bar';
import { StatTile, type StatTone } from '@/components/student/stat-tile';
import { StatusPie, type PieSlice } from '@/components/student/status-pie';
import { StreakCard } from '@/components/student/streak-card';
import { WeekdayDistributionChart } from '@/components/student/weekday-distribution-chart';
import type {
    DailyPoint,
    HeatmapDay,
    HistoryRow,
    MonthlyPoint,
    PeerComparison,
    StreakStats,
    WeekdayBlock,
} from '@/types/student-stats';

export type TileSpec = {
    label: string;
    value: number | string;
    suffix?: string;
    hint?: string;
    icon: LucideIcon;
    tone: StatTone;
};

/**
 * Tata letak satu tab statistik. Membuat tab Absensi, Dhuha, dan Dzuhur di
 * halaman detail siswa masing-masing tinggal beberapa baris, bukan ~150.
 */
export function StatsPanel({
    range,
    startDate,
    endDate,
    onStartChange,
    onEndChange,
    onApply,
    exports,
    tiles,
    daily,
    dailySeries,
    dailyTitle,
    slices,
    pieTitle,
    weekday,
    weekdayTitle,
    streaks,
    monthly,
    heatmap,
    heatmapTitle,
    comparison,
    studentRate,
    history,
    historyTitle,
    historyEmpty,
    historyShowType,
    children,
}: {
    range: string;
    startDate: string;
    endDate: string;
    onStartChange: (value: string) => void;
    onEndChange: (value: string) => void;
    onApply: () => void;
    exports: ExportLink[];
    tiles: TileSpec[];
    daily?: DailyPoint[];
    dailySeries: DailySeries[];
    dailyTitle: string;
    slices?: PieSlice[];
    pieTitle: string;
    weekday?: WeekdayBlock;
    weekdayTitle: string;
    streaks?: StreakStats;
    monthly?: MonthlyPoint[];
    heatmap?: HeatmapDay[];
    heatmapTitle: string;
    comparison?: PeerComparison;
    studentRate?: number;
    history?: HistoryRow[];
    historyTitle: string;
    historyEmpty: string;
    historyShowType?: boolean;
    children?: ReactNode;
}) {
    return (
        <div className="space-y-6">
            <RangeBar
                startDate={startDate}
                endDate={endDate}
                onStartChange={onStartChange}
                onEndChange={onEndChange}
                onApply={onApply}
                exports={exports}
            />

            {children}

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {tiles.map((tile) => (
                    <StatTile key={tile.label} {...tile} />
                ))}
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <DailyBarChart data={daily} series={dailySeries} title={dailyTitle} description={range} />
                <StatusPie slices={slices} title={pieTitle} description={range} />
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <WeekdayDistributionChart data={weekday} title={weekdayTitle} />
                <AttendanceHeatmap days={heatmap} title={heatmapTitle} />
            </div>

            {/* Tab sholat tidak punya perbandingan kelas maupun tren bulanan,
                jadi kartu Runtun berdiri sendiri — satu kolom, bukan setengah
                layar kosong di sebelahnya. */}
            <div className={cn('grid gap-6', comparison && 'lg:grid-cols-2')}>
                <StreakCard streaks={streaks} />
                {comparison && <PeerComparisonCard comparison={comparison} studentRate={studentRate} />}
            </div>

            {monthly && <MonthlyTrendChart data={monthly} />}

            <HistoryTable
                title={historyTitle}
                description="30 catatan terakhir pada rentang ini"
                rows={history}
                showType={historyShowType}
                emptyMessage={historyEmpty}
            />
        </div>
    );
}
