import { Head, router } from '@inertiajs/react';
import { CheckCircle2, Download, HardDrive, History, Loader2, RefreshCw, User, XCircle } from 'lucide-react';
import { type ReactNode, useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { dashboard } from '@/routes';

type LogEntry = {
    id: string;
    type: string;
    student_name: string;
    student_nis: string;
    layout_name: string;
    status: string;
    file_url: string | null;
    drive_url: string | null;
    generated_by: string;
    error_message: string | null;
    created_at: string;
};

type Props = {
    logs: LogEntry[];
};

const statusConfig: Record<string, { label: string; className: string; icon: ReactNode }> = {
    completed: {
        label: 'Selesai',
        className: 'border-green-200 bg-green-100 text-green-800 dark:border-green-800 dark:bg-green-900 dark:text-green-300',
        icon: <CheckCircle2 className="size-3.5" />,
    },
    failed: {
        label: 'Gagal',
        className: 'border-red-200 bg-red-100 text-red-800 dark:border-red-800 dark:bg-red-900 dark:text-red-300',
        icon: <XCircle className="size-3.5" />,
    },
    processing: {
        label: 'Proses',
        className: 'border-amber-200 bg-amber-100 text-amber-800 dark:border-amber-800 dark:bg-amber-900 dark:text-amber-300',
        icon: <Loader2 className="size-3.5 animate-spin" />,
    },
};

export default function CardGenerationIndex({ logs }: Props) {
    const hasProcessing = logs.some((log) => log.status === 'processing');

    useEffect(() => {
        if (!hasProcessing) {
            return;
        }

        let reloading = false;
        const interval = window.setInterval(() => {
            if (reloading) {
                return;
            }
            reloading = true;
            router.reload({
                only: ['logs'],
                onFinish: () => {
                    reloading = false;
                },
            });
        }, 3000);

        return () => window.clearInterval(interval);
    }, [hasProcessing]);

    return (
        <>
            <Head title="Riwayat Generate Kartu" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Riwayat Generate Kartu</h1>
                    <p className="text-muted-foreground text-sm">Log aktivitas generate kartu siswa.</p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <History className="size-4" /> Riwayat Generate
                            {hasProcessing && (
                                <span className="text-muted-foreground ml-auto inline-flex items-center gap-1 text-xs font-normal">
                                    <RefreshCw className="size-3 animate-spin" /> memperbarui…
                                </span>
                            )}
                        </CardTitle>
                        <CardDescription>Log aktivitas generate kartu terakhir (maks. 50 entri).</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {logs.length === 0 ? (
                            <div className="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed py-12 text-center">
                                <History className="text-muted-foreground/60 size-8" />
                                <p className="text-sm font-medium">Belum ada riwayat generate</p>
                                <p className="text-muted-foreground text-xs">Kartu yang di-generate akan muncul di sini sebagai log.</p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Siswa</TableHead>
                                            <TableHead>Layout</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Oleh</TableHead>
                                            <TableHead>Waktu</TableHead>
                                            <TableHead className="text-right">Berkas</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {logs.map((log) => {
                                            const status = statusConfig[log.status] ?? { label: log.status, className: '', icon: null };

                                            return (
                                                <TableRow key={log.id}>
                                                    <TableCell>
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-medium">{log.student_name}</span>
                                                            {log.type === 'photo_sheet' && (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="border-purple-200 bg-purple-100 text-purple-800 dark:border-purple-800 dark:bg-purple-900 dark:text-purple-300"
                                                                >
                                                                    Pas Foto
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        <div className="text-muted-foreground text-xs">NIS: {log.student_nis}</div>
                                                    </TableCell>
                                                    <TableCell className="text-sm">{log.type === 'photo_sheet' ? '-' : log.layout_name}</TableCell>
                                                    <TableCell>
                                                        <Badge variant="outline" className={`gap-1 ${status.className}`}>
                                                            {status.icon}
                                                            {status.label}
                                                        </Badge>
                                                        {log.status === 'failed' && log.error_message && (
                                                            <p className="text-muted-foreground mt-1 max-w-[220px] truncate text-xs" title={log.error_message}>
                                                                {log.error_message}
                                                            </p>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <span className="text-muted-foreground inline-flex items-center gap-1 text-sm">
                                                            <User className="size-3.5" />
                                                            {log.generated_by || '-'}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell className="text-muted-foreground whitespace-nowrap text-xs">{log.created_at}</TableCell>
                                                    <TableCell>
                                                        <div className="flex justify-end gap-1">
                                                            {log.drive_url && (
                                                                <Button variant="ghost" size="icon" title="Buka di Google Drive" asChild>
                                                                    <a href={log.drive_url} target="_blank" rel="noreferrer">
                                                                        <HardDrive className="size-4" />
                                                                    </a>
                                                                </Button>
                                                            )}
                                                            {log.file_url && (
                                                                <Button variant="ghost" size="icon" title="Download berkas lokal" asChild>
                                                                    <a href={log.file_url} target="_blank" rel="noreferrer">
                                                                        <Download className="size-4" />
                                                                    </a>
                                                                </Button>
                                                            )}
                                                            {!log.drive_url && !log.file_url && <span className="text-muted-foreground text-xs">-</span>}
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

CardGenerationIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Riwayat Generate', href: '/admin/card-generation' },
    ],
};
