import { CheckCircle2, XCircle } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

type CheckinRow = {
    id: number;
    studentName: string;
    className: string | null;
    time: string;
    date: string;
    status: string;
    statusColor: string;
    notificationSent: boolean;
    initials: string;
};

type LatestCheckinsTableProps = {
    data?: CheckinRow[];
};

const statusVariantMap: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    success: 'default',
    warning: 'outline',
    destructive: 'destructive',
    secondary: 'secondary',
    default: 'secondary',
};

export function LatestCheckinsTable({ data }: LatestCheckinsTableProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Check-in Terbaru</CardTitle>
                <CardDescription>10 siswa terakhir</CardDescription>
            </CardHeader>
            <CardContent>
                {!data ? (
                    <div className="space-y-3">
                        {Array.from({ length: 5 }).map((_, i) => (
                            <Skeleton key={i} className="h-12 w-full" />
                        ))}
                    </div>
                ) : data.length === 0 ? (
                    <p className="text-muted-foreground py-8 text-center text-sm">Belum ada check-in hari ini</p>
                ) : (
                    <div className="space-y-3">
                        {data.map((row) => (
                            <div key={row.id} className="flex items-center gap-3 rounded-lg border p-3">
                                <Avatar className="size-9">
                                    <AvatarFallback className="bg-primary/10 text-primary text-xs">{row.initials}</AvatarFallback>
                                </Avatar>
                                <div className="flex-1 space-y-0.5">
                                    <p className="text-sm font-medium leading-none">{row.studentName}</p>
                                    <p className="text-muted-foreground text-xs">
                                        {row.className} &middot; {row.date}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="text-muted-foreground text-xs">{row.time}</span>
                                    <Badge variant={statusVariantMap[row.statusColor] ?? 'secondary'}>{row.status}</Badge>
                                    {row.notificationSent ? (
                                        <CheckCircle2 className="size-4 text-emerald-500" />
                                    ) : (
                                        <XCircle className="size-4 text-red-400" />
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
