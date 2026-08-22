import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, Download, HardDrive, History, Loader2, RefreshCw, Search, User, XCircle } from 'lucide-react';
import { type ReactNode, useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
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

type PaginationLink = { url: string | null; label: string; active: boolean };

type Filters = {
    range: string;
    start_date: string;
    end_date: string;
    status: string;
    type: string;
    search: string;
};

type Props = {
    logs: {
        data: LogEntry[];
        links: PaginationLink[];
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: Filters;
};

const RANGE_OPTIONS: { value: string; label: string }[] = [
    { value: 'all', label: 'Semua Waktu' },
    { value: 'today', label: 'Hari Ini' },
    { value: 'week', label: 'Minggu Ini' },
    { value: 'month', label: 'Bulan Ini' },
    { value: 'custom', label: 'Rentang Sendiri' },
];

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

export default function CardGenerationIndex({ logs, filters }: Props) {
    const hasProcessing = logs.data.some((log) => log.status === 'processing');
    const [search, setSearch] = useState(filters.search);
    const [startDate, setStartDate] = useState(filters.start_date);
    const [endDate, setEndDate] = useState(filters.end_date);

    function go(next: Partial<Filters>) {
        const merged = { ...filters, ...next };

        router.get(
            '/admin/card-generation',
            {
                range: merged.range === 'all' ? undefined : merged.range,
                start_date: merged.range === 'custom' ? merged.start_date || undefined : undefined,
                end_date: merged.range === 'custom' ? merged.end_date || undefined : undefined,
                status: merged.status || undefined,
                type: merged.type || undefined,
                search: merged.search || undefined,
            },
            { preserveState: true, replace: true },
        );
    }

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
            // `only` memuat ulang props tanpa pindah URL, jadi filter yang sedang
            // aktif ikut terbawa apa adanya — tanpa ini polling akan melempar
            // operator kembali ke daftar penuh setiap tiga detik.
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

                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                    <div className="relative min-w-[200px] flex-1">
                        <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            placeholder="Cari nama atau NIS siswa..."
                            value={search}
                            onChange={(e) => {
                                setSearch(e.target.value);
                                go({ search: e.target.value });
                            }}
                            className="pl-9"
                        />
                    </div>

                    <Select value={filters.range} onValueChange={(value) => go({ range: value })}>
                        <SelectTrigger className="w-full sm:w-[170px]">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {RANGE_OPTIONS.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {filters.range === 'custom' && (
                        <>
                            <div className="grid gap-1">
                                <Label htmlFor="start_date">Dari</Label>
                                <Input
                                    id="start_date"
                                    type="date"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="end_date">Sampai</Label>
                                <Input
                                    id="end_date"
                                    type="date"
                                    value={endDate}
                                    onChange={(e) => setEndDate(e.target.value)}
                                />
                            </div>
                            <Button onClick={() => go({ start_date: startDate, end_date: endDate })}>Terapkan</Button>
                        </>
                    )}

                    <Select
                        value={filters.status || 'all'}
                        onValueChange={(value) => go({ status: value === 'all' ? '' : value })}
                    >
                        <SelectTrigger className="w-full sm:w-[150px]">
                            <SelectValue placeholder="Semua Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua Status</SelectItem>
                            <SelectItem value="completed">Selesai</SelectItem>
                            <SelectItem value="processing">Proses</SelectItem>
                            <SelectItem value="failed">Gagal</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.type || 'all'}
                        onValueChange={(value) => go({ type: value === 'all' ? '' : value })}
                    >
                        <SelectTrigger className="w-full sm:w-[150px]">
                            <SelectValue placeholder="Semua Jenis" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua Jenis</SelectItem>
                            <SelectItem value="card">Kartu</SelectItem>
                            <SelectItem value="photo_sheet">Pas Foto</SelectItem>
                            <SelectItem value="photo">Foto</SelectItem>
                        </SelectContent>
                    </Select>
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
                        <CardDescription>
                            {logs.total} entri sesuai filter. Semua waktu dalam WIB.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {logs.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed py-12 text-center">
                                <History className="text-muted-foreground/60 size-8" />
                                <p className="text-sm font-medium">Tidak ada riwayat pada filter ini</p>
                                <p className="text-muted-foreground text-xs">Ubah rentang waktunya, atau kosongkan pencarian.</p>
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
                                            <TableHead>Waktu (WIB)</TableHead>
                                            <TableHead className="text-right">Berkas</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {logs.data.map((log) => {
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

                {logs.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-muted-foreground text-sm">
                            Menampilkan {logs.from} - {logs.to} dari {logs.total} entri
                        </p>
                        <div className="flex gap-1">
                            {logs.links.map((link, index) => (
                                <Button
                                    key={index}
                                    variant={link.active ? 'default' : 'outline'}
                                    size="sm"
                                    disabled={!link.url}
                                    asChild={!!link.url}
                                >
                                    {link.url ? (
                                        <Link href={link.url} preserveState dangerouslySetInnerHTML={{ __html: link.label }} />
                                    ) : (
                                        <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                    )}
                                </Button>
                            ))}
                        </div>
                    </div>
                )}
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
