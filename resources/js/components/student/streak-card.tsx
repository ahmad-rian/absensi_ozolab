import { Flame, Snowflake } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { StreakStats } from '@/types/student-stats';

/**
 * Tiga angka skalar — `ResponsiveContainer` untuk ini hanya memboroskan bundle
 * dan ruang layout.
 */
export function StreakCard({ streaks }: { streaks?: StreakStats }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Runtun</CardTitle>
                <CardDescription>Dihitung atas hari efektif, bukan hari kalender</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {!streaks ? (
                    <Skeleton className="h-24 w-full" />
                ) : (
                    <>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant={streaks.current_kind === 'hadir' ? 'default' : 'secondary'}>
                                {streaks.current_kind === 'hadir' ? 'Sedang hadir' : 'Sedang absen'} ·{' '}
                                {streaks.current_length} hari
                            </Badge>
                            {streaks.last_absent_date && (
                                <span className="text-muted-foreground text-xs">
                                    Terakhir tidak hadir: {streaks.last_absent_date}
                                </span>
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <StreakStat
                                icon={Flame}
                                label="Runtun hadir terpanjang"
                                value={streaks.longest_present}
                                tone="text-chart-2"
                            />
                            <StreakStat
                                icon={Snowflake}
                                label="Runtun absen terpanjang"
                                value={streaks.longest_absent}
                                tone="text-chart-5"
                            />
                        </div>
                    </>
                )}
            </CardContent>
        </Card>
    );
}

function StreakStat({
    icon: Icon,
    label,
    value,
    tone,
}: {
    icon: typeof Flame;
    label: string;
    value: number;
    tone: string;
}) {
    return (
        <div className="flex items-start gap-2">
            <Icon className={`mt-0.5 size-4 ${tone}`} />
            <div>
                <p className="text-2xl font-bold tabular-nums">{value}</p>
                <p className="text-muted-foreground text-xs">{label}</p>
            </div>
        </div>
    );
}
