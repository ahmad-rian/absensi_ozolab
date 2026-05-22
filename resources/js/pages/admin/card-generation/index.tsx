import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, CreditCard, Download, ExternalLink, HardDrive, Loader2, Printer } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { dashboard } from '@/routes';

type Layout = { id: number; name: string; type: string };
type Classroom = { id: number; name: string };
type LogEntry = {
    id: number;
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
    layouts: Layout[];
    classrooms: Classroom[];
    logs: LogEntry[];
    driveConfigured: boolean;
};

export default function CardGenerationIndex({ layouts, classrooms, logs, driveConfigured }: Props) {
    const [selectedStudents, setSelectedStudents] = useState<'all' | 'classroom'>('all');

    const form = useForm({
        layout_id: '',
        classroom_id: '',
        student_ids: [] as number[],
    });

    function handleGenerate(e: FormEvent) {
        e.preventDefault();
        // For now, we generate for all students or by classroom
        // The controller will handle fetching student IDs
        const url = '/admin/card-generation/generate';
        router.post(url, {
            layout_id: form.data.layout_id,
            student_ids: form.data.student_ids.length > 0 ? form.data.student_ids : ['all'],
            classroom_id: form.data.classroom_id || undefined,
        }, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Generate Kartu" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Generate Kartu Siswa</h1>
                    <p className="text-muted-foreground text-sm">
                        Buat kartu siswa secara batch berdasarkan layout yang sudah dibuat.
                    </p>
                </div>

                {!driveConfigured && (
                    <Alert className="border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950">
                        <AlertTriangle className="size-4 text-amber-600" />
                        <AlertDescription className="flex items-center justify-between">
                            <span className="text-amber-800 dark:text-amber-200">
                                Google Drive belum dikonfigurasi. Kartu akan disimpan lokal saja, tidak otomatis upload ke Drive.
                            </span>
                            <Button variant="outline" size="sm" asChild className="ml-3 shrink-0">
                                <Link href="/admin/drive-config">
                                    <HardDrive className="mr-1.5 size-3.5" /> Setup Drive
                                </Link>
                            </Button>
                        </AlertDescription>
                    </Alert>
                )}

                {/* Generate Form */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Printer className="size-4" /> Batch Generate
                        </CardTitle>
                        <CardDescription>
                            Pilih layout dan target siswa, lalu klik generate.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleGenerate} className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label>Layout Kartu</Label>
                                    <Select value={form.data.layout_id} onValueChange={(v) => form.setData('layout_id', v)}>
                                        <SelectTrigger><SelectValue placeholder="Pilih layout" /></SelectTrigger>
                                        <SelectContent>
                                            {layouts.map((l) => (
                                                <SelectItem key={l.id} value={String(l.id)}>{l.name} ({l.type})</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid gap-2">
                                    <Label>Filter Kelas (opsional)</Label>
                                    <Select value={form.data.classroom_id || 'all'} onValueChange={(v) => form.setData('classroom_id', v === 'all' ? '' : v)}>
                                        <SelectTrigger><SelectValue placeholder="Semua kelas" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Semua Kelas</SelectItem>
                                            {classrooms.map((c) => (
                                                <SelectItem key={c.id} value={String(c.id)}>{c.name}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <Button type="submit" disabled={!form.data.layout_id || form.processing} className="gap-2">
                                {form.processing ? <Loader2 className="size-4 animate-spin" /> : <CreditCard className="size-4" />}
                                Generate Kartu
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                {/* Generation Logs */}
                {logs.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Riwayat Generate ({logs.length})</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Siswa</TableHead>
                                            <TableHead>NIS</TableHead>
                                            <TableHead>Layout</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Tanggal</TableHead>
                                            <TableHead>Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {logs.map((log) => (
                                            <TableRow key={log.id}>
                                                <TableCell className="font-medium">{log.student_name}</TableCell>
                                                <TableCell>{log.student_nis}</TableCell>
                                                <TableCell>{log.layout_name}</TableCell>
                                                <TableCell>
                                                    <Badge variant={log.status === 'completed' ? 'default' : log.status === 'failed' ? 'destructive' : 'secondary'}>
                                                        {log.status === 'completed' ? 'Selesai' : log.status === 'failed' ? 'Gagal' : 'Proses'}
                                                    </Badge>
                                                    {log.error_message && (
                                                        <p className="mt-1 max-w-xs truncate text-xs text-red-500" title={log.error_message}>{log.error_message}</p>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-xs">{log.created_at}</TableCell>
                                                <TableCell>
                                                    <div className="flex gap-1">
                                                        {log.file_url && (
                                                            <a href={log.file_url} target="_blank" rel="noreferrer">
                                                                <Button variant="ghost" size="icon" title="Download">
                                                                    <Download className="size-4" />
                                                                </Button>
                                                            </a>
                                                        )}
                                                        {log.drive_url && (
                                                            <a href={log.drive_url} target="_blank" rel="noreferrer">
                                                                <Button variant="ghost" size="icon" title="Buka di Drive">
                                                                    <ExternalLink className="size-4" />
                                                                </Button>
                                                            </a>
                                                        )}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

CardGenerationIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Generate Kartu', href: '/admin/card-generation' },
    ],
};
