import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowUpRight,
    CheckCircle2,
    FileSpreadsheet,
    Upload,
    UserMinus,
    XCircle,
} from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

type PromotionRow = {
    row_number: number;
    action: 'promote' | 'reject';
    reason: string | null;
    student_id: string | null;
    student_name: string | null;
    nisn: string;
    from_classroom: string | null;
    to_classroom: string;
    classroom_id: string | null;
};

type MissingStudent = {
    student_id: string;
    nisn: string;
    full_name: string;
    classroom: string | null;
};

type Preview = {
    key: string;
    filename: string;
    rows: PromotionRow[];
    missing: MissingStudent[];
    summary: {
        promote: number;
        reject: number;
        missing: number;
        total: number;
    };
};

type PageProps = {
    academic_year: { id: string; name: string } | null;
    preview: Preview | null;
    errors: Record<string, string>;
};

export default function KenaikanKelas({
    academic_year,
    preview,
    errors,
}: PageProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [uploading, setUploading] = useState(false);
    const [applying, setApplying] = useState(false);

    const promoteRows =
        preview?.rows.filter((row) => row.action === 'promote') ?? [];
    const rejectRows =
        preview?.rows.filter((row) => row.action === 'reject') ?? [];
    const missingRows = preview?.missing ?? [];

    function handleUpload(e: FormEvent) {
        e.preventDefault();

        if (!file) {
            return;
        }

        const formData = new FormData();
        formData.append('file', file);

        router.post('/admin/kelas/kenaikan', formData, {
            onStart: () => setUploading(true),
            onFinish: () => {
                setUploading(false);
                setFile(null);

                if (fileInputRef.current) {
                    fileInputRef.current.value = '';
                }
            },
        });
    }

    function handleApply() {
        if (!preview) {
            return;
        }

        router.post(
            `/admin/kelas/kenaikan/${preview.key}/apply`,
            {},
            {
                onStart: () => setApplying(true),
                onFinish: () => setApplying(false),
            },
        );
    }

    return (
        <>
            <Head title="Kenaikan Kelas" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Kenaikan Kelas
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Unggah berkas berisi NISN dan kelas baru, periksa
                        hasilnya, lalu terapkan.
                    </p>
                </div>

                {!academic_year && (
                    <Alert variant="destructive">
                        <AlertTriangle className="size-4" />
                        <AlertTitle>Belum ada tahun ajaran aktif</AlertTitle>
                        <AlertDescription>
                            Aktifkan dulu tahun ajaran berjalan di halaman Kelas
                            sebelum menaikkan kelas siswa.
                        </AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <FileSpreadsheet className="size-4" />
                            Unggah Berkas
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleUpload} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="promotion-file">
                                    Berkas .xlsx atau .csv
                                </Label>
                                <Input
                                    id="promotion-file"
                                    ref={fileInputRef}
                                    type="file"
                                    accept=".xlsx,.csv,.txt"
                                    onChange={(e) =>
                                        setFile(e.target.files?.[0] ?? null)
                                    }
                                />
                                <p className="text-sm text-muted-foreground">
                                    Kolom yang dibaca hanya{' '}
                                    <strong>NISN</strong> dan{' '}
                                    <strong>Kelas Baru</strong> (boleh ditulis
                                    "kelas", "rombel", atau "kelas tujuan").
                                    Kelas tujuan harus sudah ada di tahun ajaran{' '}
                                    {academic_year?.name ?? 'aktif'}.
                                </p>
                                <InputError message={errors.file} />
                            </div>
                            <Button
                                type="submit"
                                disabled={!file || uploading || !academic_year}
                            >
                                <Upload />
                                {uploading ? 'Memeriksa…' : 'Periksa Berkas'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                {preview && (
                    <>
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant="secondary">
                                    {preview.filename}
                                </Badge>
                                <Badge variant="secondary">
                                    {preview.summary.promote} akan diubah
                                </Badge>
                                <Badge variant="secondary">
                                    {preview.summary.missing} tidak ada di
                                    berkas
                                </Badge>
                                <Badge variant="secondary">
                                    {preview.summary.reject} ditolak
                                </Badge>
                            </div>
                            <Button
                                onClick={handleApply}
                                disabled={
                                    applying || preview.summary.promote === 0
                                }
                            >
                                <ArrowUpRight />
                                {applying
                                    ? 'Menerapkan…'
                                    : `Naikkan ${preview.summary.promote} Siswa`}
                            </Button>
                        </div>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <CheckCircle2 className="size-4" />
                                    Akan diubah ({promoteRows.length})
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {promoteRows.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Tidak ada siswa yang bisa dinaikkan dari
                                        berkas ini.
                                    </p>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Baris</TableHead>
                                                    <TableHead>NISN</TableHead>
                                                    <TableHead>Nama</TableHead>
                                                    <TableHead>
                                                        Kelas Lama
                                                    </TableHead>
                                                    <TableHead>
                                                        Kelas Baru
                                                    </TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {promoteRows.map((row) => (
                                                    <TableRow
                                                        key={`promote-${row.row_number}`}
                                                    >
                                                        <TableCell>
                                                            {row.row_number}
                                                        </TableCell>
                                                        <TableCell>
                                                            {row.nisn}
                                                        </TableCell>
                                                        <TableCell>
                                                            {row.student_name}
                                                        </TableCell>
                                                        <TableCell className="text-muted-foreground">
                                                            {row.from_classroom ??
                                                                '—'}
                                                        </TableCell>
                                                        <TableCell className="font-medium">
                                                            {row.to_classroom}
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <UserMinus className="size-4" />
                                    Tidak ada di berkas ({missingRows.length})
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <p className="text-sm text-muted-foreground">
                                    Kelas siswa berikut{' '}
                                    <strong>tidak diubah</strong>. Periksa
                                    apakah memang sengaja dilewati (mis. lulus
                                    atau pindah) atau berkasnya kurang lengkap.
                                </p>
                                {missingRows.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Semua siswa aktif ada di berkas.
                                    </p>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>NISN</TableHead>
                                                    <TableHead>Nama</TableHead>
                                                    <TableHead>
                                                        Kelas Sekarang
                                                    </TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {missingRows.map((student) => (
                                                    <TableRow
                                                        key={student.student_id}
                                                    >
                                                        <TableCell>
                                                            {student.nisn ||
                                                                '—'}
                                                        </TableCell>
                                                        <TableCell>
                                                            {student.full_name}
                                                        </TableCell>
                                                        <TableCell className="text-muted-foreground">
                                                            {student.classroom ??
                                                                '—'}
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <XCircle className="size-4" />
                                    Ditolak ({rejectRows.length})
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {rejectRows.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Tidak ada baris yang ditolak.
                                    </p>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Baris</TableHead>
                                                    <TableHead>NISN</TableHead>
                                                    <TableHead>
                                                        Kelas Baru
                                                    </TableHead>
                                                    <TableHead>
                                                        Alasan
                                                    </TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {rejectRows.map((row) => (
                                                    <TableRow
                                                        key={`reject-${row.row_number}`}
                                                    >
                                                        <TableCell>
                                                            {row.row_number}
                                                        </TableCell>
                                                        <TableCell>
                                                            {row.nisn || '—'}
                                                        </TableCell>
                                                        <TableCell>
                                                            {row.to_classroom ||
                                                                '—'}
                                                        </TableCell>
                                                        <TableCell className="text-destructive">
                                                            {row.reason}
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </>
    );
}

KenaikanKelas.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Kelas', href: '/admin/kelas' },
        { title: 'Kenaikan Kelas', href: '/admin/kelas/kenaikan' },
    ],
};
