import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Download,
    FileSpreadsheet,
    History,
    Loader2,
    RefreshCw,
    Upload,
    UserPlus,
    XCircle,
} from 'lucide-react';
import type { ChangeEvent, FormEvent, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';

type PreviewRow = {
    row_number: number;
    full_name: string | null;
    nis: string | null;
    nisn: string | null;
    classroom_name: string | null;
    existing_name: string | null;
    reason: string | null;
};

type Preview = {
    key: string;
    filename: string;
    summary: { create: number; update: number; reject: number; total: number };
    groups: {
        create: PreviewRow[];
        update: PreviewRow[];
        reject: PreviewRow[];
    };
};

type ImportJob = {
    id: string;
    filename: string;
    status: string;
    total_rows: number;
    created_count: number;
    updated_count: number;
    failed_count: number;
    errors: { row: number; message: string }[];
    created_by: string;
    created_at: string | null;
};

type Props = {
    jobs: ImportJob[];
    preview: Preview | null;
    errors: Record<string, string>;
};

const statusConfig: Record<
    string,
    { label: string; className: string; icon: ReactNode }
> = {
    done: {
        label: 'Selesai',
        className:
            'border-green-200 bg-green-100 text-green-800 dark:border-green-800 dark:bg-green-900 dark:text-green-300',
        icon: <CheckCircle2 className="size-3.5" />,
    },
    failed: {
        label: 'Gagal',
        className:
            'border-red-200 bg-red-100 text-red-800 dark:border-red-800 dark:bg-red-900 dark:text-red-300',
        icon: <XCircle className="size-3.5" />,
    },
    processing: {
        label: 'Proses',
        className:
            'border-amber-200 bg-amber-100 text-amber-800 dark:border-amber-800 dark:bg-amber-900 dark:text-amber-300',
        icon: <Loader2 className="size-3.5 animate-spin" />,
    },
    pending: {
        label: 'Menunggu',
        className:
            'border-slate-200 bg-slate-100 text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300',
        icon: <Loader2 className="size-3.5 animate-spin" />,
    },
};

const groupConfig = [
    {
        key: 'create' as const,
        title: 'Akan dibuat',
        description:
            'Siswa baru yang belum punya NISN/NIS cocok di sekolah ini.',
        icon: <UserPlus className="size-4 text-green-600" />,
    },
    {
        key: 'update' as const,
        title: 'Akan diperbarui',
        description:
            'Cocok dengan siswa lama. Kolom kosong di berkas dibiarkan apa adanya.',
        icon: <RefreshCw className="size-4 text-blue-600" />,
    },
    {
        key: 'reject' as const,
        title: 'Ditolak',
        description:
            'Baris ini dilewati. Perbaiki berkasnya lalu unggah ulang bila perlu.',
        icon: <AlertTriangle className="size-4 text-red-600" />,
    },
];

export default function SiswaImport({ jobs, preview, errors }: Props) {
    const [file, setFile] = useState<File | null>(null);
    const [uploading, setUploading] = useState(false);
    const [applying, setApplying] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const hasProcessing = jobs.some(
        (job) => job.status === 'processing' || job.status === 'pending',
    );

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
                only: ['jobs'],
                onFinish: () => {
                    reloading = false;
                },
            });
        }, 3000);

        return () => window.clearInterval(interval);
    }, [hasProcessing]);

    function handleUpload(e: FormEvent) {
        e.preventDefault();

        if (!file) {
            return;
        }

        const formData = new FormData();
        formData.append('file', file);

        router.post('/admin/siswa/import', formData, {
            forceFormData: true,
            onStart: () => setUploading(true),
            onFinish: () => setUploading(false),
            onError: () => {
                setFile(null);

                if (fileInputRef.current) {
                    fileInputRef.current.value = '';
                }
            },
        });
    }

    function handleFileChange(e: ChangeEvent<HTMLInputElement>) {
        setFile(e.target.files?.[0] ?? null);
    }

    function handleApply() {
        if (!preview) {
            return;
        }

        router.post(
            `/admin/siswa/import/${preview.key}/apply`,
            {},
            {
                onStart: () => setApplying(true),
                onFinish: () => setApplying(false),
            },
        );
    }

    return (
        <>
            <Head title="Impor Siswa" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Impor Siswa
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Unggah berkas Excel/CSV untuk menambah dan memperbarui
                        data siswa sekaligus.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <FileSpreadsheet className="size-4" /> Unggah Berkas
                        </CardTitle>
                        <CardDescription>
                            Format .xlsx atau .csv, maksimal 5 MB dan 5.000
                            baris. Siswa dicocokkan lewat NISN, lalu NIS.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={handleUpload}
                            className="flex flex-col gap-4"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="import-file">
                                    Berkas siswa
                                </Label>
                                <Input
                                    id="import-file"
                                    ref={fileInputRef}
                                    type="file"
                                    accept=".xlsx,.csv,.txt"
                                    onChange={handleFileChange}
                                    disabled={uploading}
                                />
                                <InputError message={errors?.file} />
                            </div>

                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="submit"
                                    disabled={!file || uploading}
                                >
                                    {uploading ? (
                                        <Loader2 className="size-4 animate-spin" />
                                    ) : (
                                        <Upload className="size-4" />
                                    )}
                                    Periksa Berkas
                                </Button>
                                <Button type="button" variant="outline" asChild>
                                    <a href="/admin/siswa/import/template">
                                        <Download className="size-4" /> Unduh
                                        Template Excel
                                    </a>
                                </Button>
                                <Button type="button" variant="ghost" asChild>
                                    <Link href="/admin/siswa">
                                        Kembali ke Daftar Siswa
                                    </Link>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {preview && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Pratinjau: {preview.filename}
                            </CardTitle>
                            <CardDescription>
                                {preview.summary.total} baris terbaca —{' '}
                                {preview.summary.create} dibuat,{' '}
                                {preview.summary.update} diperbarui,{' '}
                                {preview.summary.reject} ditolak. Belum ada yang
                                tersimpan sampai kamu menekan Terapkan.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
                            {groupConfig.map((group) => {
                                const rows = preview.groups[group.key];

                                return (
                                    <div
                                        key={group.key}
                                        className="flex flex-col gap-2"
                                    >
                                        <div className="flex items-center gap-2">
                                            {group.icon}
                                            <h2 className="text-sm font-semibold">
                                                {group.title} ({rows.length})
                                            </h2>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {group.description}
                                        </p>

                                        {rows.length === 0 ? (
                                            <p className="rounded-lg border border-dashed px-3 py-4 text-center text-xs text-muted-foreground">
                                                Tidak ada baris pada kelompok
                                                ini.
                                            </p>
                                        ) : (
                                            <div className="overflow-x-auto">
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead className="w-20">
                                                                Baris
                                                            </TableHead>
                                                            <TableHead>
                                                                Nama
                                                            </TableHead>
                                                            <TableHead>
                                                                NISN / NIS
                                                            </TableHead>
                                                            <TableHead>
                                                                Kelas
                                                            </TableHead>
                                                            <TableHead>
                                                                {group.key ===
                                                                'reject'
                                                                    ? 'Alasan ditolak'
                                                                    : 'Keterangan'}
                                                            </TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {rows.map((row) => (
                                                            <TableRow
                                                                key={`${group.key}-${row.row_number}`}
                                                            >
                                                                <TableCell className="text-xs text-muted-foreground">
                                                                    {
                                                                        row.row_number
                                                                    }
                                                                </TableCell>
                                                                <TableCell className="font-medium">
                                                                    {row.full_name ??
                                                                        row.existing_name ??
                                                                        '-'}
                                                                </TableCell>
                                                                <TableCell className="text-xs">
                                                                    {row.nisn ??
                                                                        '-'}{' '}
                                                                    /{' '}
                                                                    {row.nis ??
                                                                        '-'}
                                                                </TableCell>
                                                                <TableCell className="text-sm">
                                                                    {row.classroom_name ??
                                                                        '-'}
                                                                </TableCell>
                                                                <TableCell className="text-xs text-muted-foreground">
                                                                    {group.key ===
                                                                    'reject'
                                                                        ? row.reason
                                                                        : group.key ===
                                                                            'update'
                                                                          ? `Menimpa data ${row.existing_name ?? 'siswa terkait'}`
                                                                          : 'Siswa baru'}
                                                                </TableCell>
                                                            </TableRow>
                                                        ))}
                                                    </TableBody>
                                                </Table>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}

                            <div className="flex flex-wrap items-center gap-2 border-t pt-4">
                                <Button
                                    type="button"
                                    onClick={handleApply}
                                    disabled={
                                        applying ||
                                        preview.summary.create +
                                            preview.summary.update ===
                                            0
                                    }
                                >
                                    {applying ? (
                                        <Loader2 className="size-4 animate-spin" />
                                    ) : (
                                        <CheckCircle2 className="size-4" />
                                    )}
                                    Terapkan{' '}
                                    {preview.summary.create +
                                        preview.summary.update}{' '}
                                    Baris
                                </Button>
                                <Button type="button" variant="ghost" asChild>
                                    <Link href="/admin/siswa/import">
                                        Batalkan
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <History className="size-4" /> Riwayat Impor
                            {hasProcessing && (
                                <span className="ml-auto inline-flex items-center gap-1 text-xs font-normal text-muted-foreground">
                                    <RefreshCw className="size-3 animate-spin" />{' '}
                                    memperbarui…
                                </span>
                            )}
                        </CardTitle>
                        <CardDescription>
                            20 impor terakhir di sekolah ini.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {jobs.length === 0 ? (
                            <div className="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed py-12 text-center">
                                <History className="size-8 text-muted-foreground/60" />
                                <p className="text-sm font-medium">
                                    Belum ada riwayat impor
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Berkas yang sudah diterapkan akan muncul di
                                    sini.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Berkas</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Hasil</TableHead>
                                            <TableHead>Oleh</TableHead>
                                            <TableHead>Waktu</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {jobs.map((job) => {
                                            const status = statusConfig[
                                                job.status
                                            ] ?? {
                                                label: job.status,
                                                className: '',
                                                icon: null,
                                            };

                                            return (
                                                <TableRow key={job.id}>
                                                    <TableCell>
                                                        <span className="font-medium">
                                                            {job.filename}
                                                        </span>
                                                        <div className="text-xs text-muted-foreground">
                                                            {job.total_rows}{' '}
                                                            baris
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge
                                                            variant="outline"
                                                            className={`gap-1 ${status.className}`}
                                                        >
                                                            {status.icon}
                                                            {status.label}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="text-xs">
                                                        <span className="text-green-700 dark:text-green-400">
                                                            +{job.created_count}{' '}
                                                            baru
                                                        </span>
                                                        {' · '}
                                                        <span className="text-blue-700 dark:text-blue-400">
                                                            {job.updated_count}{' '}
                                                            diperbarui
                                                        </span>
                                                        {job.failed_count >
                                                            0 && (
                                                            <>
                                                                {' · '}
                                                                <span className="text-red-700 dark:text-red-400">
                                                                    {
                                                                        job.failed_count
                                                                    }{' '}
                                                                    gagal
                                                                </span>
                                                            </>
                                                        )}
                                                        {job.errors.length >
                                                            0 && (
                                                            <ul className="mt-1 list-inside list-disc text-muted-foreground">
                                                                {job.errors.map(
                                                                    (error) => (
                                                                        <li
                                                                            key={`${job.id}-${error.row}-${error.message}`}
                                                                            className="truncate"
                                                                        >
                                                                            Baris{' '}
                                                                            {
                                                                                error.row
                                                                            }
                                                                            :{' '}
                                                                            {
                                                                                error.message
                                                                            }
                                                                        </li>
                                                                    ),
                                                                )}
                                                            </ul>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-sm">
                                                        {job.created_by}
                                                    </TableCell>
                                                    <TableCell className="text-xs whitespace-nowrap text-muted-foreground">
                                                        {job.created_at}
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

SiswaImport.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Siswa', href: '/admin/siswa' },
        { title: 'Impor Siswa', href: '/admin/siswa/import' },
    ],
};
