import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type { HeatmapDay } from '@/types/student-stats';

// Bukan Recharts: kalender heatmap tidak punya primitif di sana, dan grid CSS
// tujuh kolom sudah cukup tanpa menambah dependensi.
const STATE_STYLE: Record<string, { className: string; label: string }> = {
    hadir: { className: 'bg-chart-2', label: 'Hadir' },
    terlambat: { className: 'bg-chart-3', label: 'Terlambat' },
    izin: { className: 'bg-chart-1', label: 'Izin' },
    sakit: { className: 'bg-chart-4', label: 'Sakit' },
    alpa: { className: 'bg-chart-5', label: 'Alpa' },
    tidak_hadir: { className: 'bg-chart-5', label: 'Tidak ikut' },
    tanpa_keterangan: { className: 'bg-muted-foreground/30', label: 'Tanpa catatan' },
};

const WEEKDAY_HEADS = ['S', 'S', 'R', 'K', 'J', 'S', 'M'];

export function AttendanceHeatmap({ days, title }: { days?: HeatmapDay[]; title: string }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>Satu kotak per hari efektif sekolah</CardDescription>
            </CardHeader>
            <CardContent>
                {!days ? (
                    <Skeleton className="h-[180px] w-full" />
                ) : days.length === 0 ? (
                    <p className="text-muted-foreground flex h-[180px] items-center justify-center text-sm">
                        Belum ada data pada rentang ini.
                    </p>
                ) : (
                    <div className="space-y-3">
                        <div className="grid grid-cols-7 gap-1.5">
                            {WEEKDAY_HEADS.map((head, index) => (
                                <span key={index} className="text-muted-foreground text-center text-[10px] font-medium">
                                    {head}
                                </span>
                            ))}

                            {days.map((day) => {
                                const style = STATE_STYLE[day.state] ?? STATE_STYLE.tanpa_keterangan;

                                return (
                                    <Tooltip key={day.date}>
                                        <TooltipTrigger asChild>
                                            <span
                                                className={cn('aspect-square w-full rounded-sm', style.className)}
                                                style={{ gridColumnStart: weekdayColumn(day.date) }}
                                            />
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            {day.date} · {style.label}
                                        </TooltipContent>
                                    </Tooltip>
                                );
                            })}
                        </div>

                        <div className="flex flex-wrap gap-3">
                            {Object.entries(STATE_STYLE)
                                .filter(([key]) => days.some((day) => day.state === key))
                                .map(([key, style]) => (
                                    <span key={key} className="text-muted-foreground flex items-center gap-1.5 text-xs">
                                        <span className={cn('size-2.5 rounded-sm', style.className)} />
                                        {style.label}
                                    </span>
                                ))}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

/**
 * Menempatkan kotak di kolom hari yang benar. Hari efektif bisa berlubang
 * (libur), jadi posisinya harus dari tanggalnya, bukan dari urutan array.
 */
function weekdayColumn(date: string): number {
    const day = new Date(`${date}T00:00:00`).getDay();

    // getDay(): 0 = Minggu. Grid mulai dari Senin.
    return day === 0 ? 7 : day;
}
