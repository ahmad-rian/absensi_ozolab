import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { PeerComparison } from '@/types/student-stats';

/**
 * Dua nilai berdampingan — meter datar terbaca lebih cepat daripada chart, dan
 * bahasanya sejalan dengan dashboard/class-comparison-bar.
 */
export function PeerComparisonCard({
    comparison,
    studentRate,
}: {
    comparison?: PeerComparison;
    studentRate?: number;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Perbandingan Kelas</CardTitle>
                <CardDescription>
                    {comparison?.class_name
                        ? `${comparison.class_name} · ${comparison.class_size} siswa`
                        : 'Siswa ini belum masuk kelas mana pun'}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {!comparison ? (
                    <Skeleton className="h-24 w-full" />
                ) : (
                    <>
                        <Meter label="Siswa ini" value={studentRate ?? 0} className="bg-chart-1" />
                        <Meter label="Rata-rata kelas" value={comparison.class_rate ?? 0} className="bg-chart-4" />
                        {comparison.class_late_rate !== null && (
                            <p className="text-muted-foreground text-xs">
                                Keterlambatan rata-rata kelas: {comparison.class_late_rate}%
                            </p>
                        )}
                    </>
                )}
            </CardContent>
        </Card>
    );
}

function Meter({ label, value, className }: { label: string; value: number; className: string }) {
    return (
        <div className="space-y-1.5">
            <div className="flex items-center justify-between text-sm">
                <span className="text-muted-foreground">{label}</span>
                <span className="font-semibold tabular-nums">{value}%</span>
            </div>
            <div className="bg-muted h-2 w-full overflow-hidden rounded-full">
                <div className={`h-full rounded-full ${className}`} style={{ width: `${Math.min(value, 100)}%` }} />
            </div>
        </div>
    );
}
