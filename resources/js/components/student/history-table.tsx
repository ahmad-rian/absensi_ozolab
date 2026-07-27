import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { HistoryRow } from '@/types/student-stats';

export const statusBadgeClass: Record<string, string> = {
    HADIR: 'border-green-500/30 bg-green-500/10 text-green-700 dark:text-green-400',
    TERLAMBAT: 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400',
    ALPA: 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-400',
    IZIN: 'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-400',
    SAKIT: 'border-purple-500/30 bg-purple-500/10 text-purple-700 dark:text-purple-400',
};

/**
 * Tabel riwayat dipakai bersama oleh tab absensi dan tiap tab sholat; dulu
 * blok Card+Table ini tersalin verbatim per tab.
 */
export function HistoryTable({
    title,
    description,
    rows,
    showType = false,
    emptyMessage,
}: {
    title: string;
    description: string;
    rows?: HistoryRow[];
    showType?: boolean;
    emptyMessage: string;
}) {
    const columns = showType ? 5 : 4;

    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Tanggal</TableHead>
                            {showType && <TableHead>Jenis</TableHead>}
                            <TableHead>Status</TableHead>
                            <TableHead>Jam</TableHead>
                            <TableHead>Perangkat</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {!rows ? (
                            <TableRow>
                                <TableCell colSpan={columns} className="text-muted-foreground py-8 text-center">
                                    Memuat…
                                </TableCell>
                            </TableRow>
                        ) : rows.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={columns} className="text-muted-foreground py-8 text-center">
                                    {emptyMessage}
                                </TableCell>
                            </TableRow>
                        ) : (
                            rows.map((row) => (
                                <TableRow key={row.id}>
                                    <TableCell className="font-medium">{row.date}</TableCell>
                                    {showType && <TableCell>{row.type_label ?? '-'}</TableCell>}
                                    <TableCell>
                                        <Badge variant="outline" className={statusBadgeClass[row.status] ?? ''}>
                                            {row.status_label}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="font-mono">{row.time ?? '-'}</TableCell>
                                    <TableCell className="text-muted-foreground text-xs">
                                        {row.device_id ?? '-'}
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    );
}
