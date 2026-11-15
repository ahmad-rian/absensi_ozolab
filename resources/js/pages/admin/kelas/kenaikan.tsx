import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowUpRight,
    CheckCircle2,
    Download,
    FileSpreadsheet,
    GraduationCap,
    Upload,
    UserMinus,
    XCircle,
} from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { GuideDialog } from '@/components/panduan/guide-dialog';
import { KenaikanKelas as PanduanKenaikanKelas } from '@/components/panduan/sections/alur';
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
import { pengaturan } from '@/routes/admin';

type PromotionRow = {
    row_number: number;
    action: 'promote' | 'graduate' | 'reject';
    reason: string | null;
    student_id: string | null;
    student_name: string | null;
    nisn: string;
    nis?: string;
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
        graduate?: number;
        reject: number;
        missing: number;
        total: number;
    };
};

type Hasil = {
    key: string;
    filename: string;
    summary: {
        promoted: number;
        graduated: number;
        failed: number;
        rejected: number;
        missing: number;
        schedules_copied: number;
    };
    failures: PromotionRow[];
};

type PageProps = {
    academic_year: { id: string; name: string } | null;
    preview: Preview | null;
    hasil: Hasil | null;
    errors: Record<string, string>;
};

export default function KenaikanKelas({
    academic_year,
    preview,
    hasil,
    errors,
}: PageProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [uploading, setUploading] = useState(false);
    const [applying, setApplying] = useState(false);

    const promoteRows =
        preview?.rows.filter((row) => row.action === 'promote') ?? [];
    const graduateRows =
        preview?.rows.filter((row) => row.action === 'graduate') ?? [];
    const rejectRows =
        preview?.rows.filter((row) => row.action === 'reject') ?? [];
    const jumlahDiterapkan = promoteRows.length + graduateRows.length;
    const unduh = (key: string, jenis: 'ditolak' | 'tidak-ada') =>
        `/admin/kelas/kenaikan/${key}/unduh/${jenis}`;
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
                    <div className="flex items-center gap-2">
                        <h1 className="text-2xl font-bold tracking-tight">
                            Kenaikan Kelas
                        </h1>
                        <GuideDialog
                            title="Cara pakai Kenaikan Kelas"
                            description="Memindahkan siswa ke kelas tahun ajaran baru dan meluluskan kelas akhir."
                            topik="kenaikan-kelas"
                        >
                            <PanduanKenaikanKelas />
                        </GuideDialog>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Unduh template, isi kolom Kelas Baru, unggah, periksa
                        hasilnya, lalu terapkan.
                    </p>
                </div>

                {!academic_year && (
                    <Alert variant="destructive">
                        <AlertTriangle className="size-4" />
                        <AlertTitle>Belum ada tahun ajaran aktif</AlertTitle>
                        <AlertDescription>
                            <p>
                                Aktifkan dulu tahun ajaran berjalan di
                                Pengaturan → tab Umum sebelum menaikkan kelas
                                siswa.
                            </p>
                            <Button
                                asChild
                                size="sm"
                                variant="outline"
                                className="mt-2 text-foreground"
                            >
                                <Link
                                    href={pengaturan({
                                        query: { tab: 'umum' },
                                    })}
                                >
                                    <ArrowUpRight className="size-4" /> Buka
                                    Pengaturan → Umum
                                </Link>
                            </Button>
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
                                <ul className="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                                    <li>
                                        Template sudah berisi semua siswa aktif.
                                        Cukup isi kolom{' '}
                                        <strong>Kelas Baru</strong>.
                                    </li>
                                    <li>
                                        Kelas tujuan harus sudah dibuat di tahun
                                        ajaran {academic_year?.name ?? 'aktif'}.
                                        Daftarnya ada di sheet kedua template.
                                    </li>
                                    <li>
                                        Siswa kelas akhir: tulis{' '}
                                        <strong>LULUS</strong>. Siswanya
                                        dinonaktifkan, riwayat absensinya tetap
                                        ada.
                                    </li>
                                    <li>
                                        Siswa dicocokkan lewat{' '}
                                        <strong>NISN</strong>. Kalau NISN-nya
                                        kosong, dipakai <strong>NIS</strong>.
                                    </li>
                                </ul>
                                <InputError message={errors.file} />
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="submit"
                                    disabled={
                                        !file || uploading || !academic_year
                                    }
                                >
                                    <Upload />
                                    {uploading
                                        ? 'Memeriksa…'
                                        : 'Periksa Berkas'}
                                </Button>
                                <Button type="button" variant="outline" asChild>
                                    <a href="/admin/kelas/kenaikan/template">
                                        <Download />
                                        Unduh Template Excel
                                    </a>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {hasil && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <CheckCircle2 className="size-4" />
                                Hasil penerapan — {hasil.filename}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex flex-wrap gap-2">
                                <Badge variant="secondary">
                                    {hasil.summary.promoted} naik kelas
                                </Badge>
                                <Badge variant="secondary">
                                    {hasil.summary.graduated} lulus
                                </Badge>
                                <Badge
                                    variant={
                                        hasil.summary.failed > 0
                                            ? 'destructive'
                                            : 'secondary'
                                    }
                                >
                                    {hasil.summary.failed} gagal diproses
                                </Badge>
                                <Badge variant="secondary">
                                    {hasil.summary.rejected} baris ditolak
                                </Badge>
                                <Badge variant="secondary">
                                    {hasil.summary.missing} tidak ada di berkas
                                </Badge>
                            </div>
                            <ul className="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                                {hasil.summary.schedules_copied > 0 && (
                                    <li>
                                        {hasil.summary.schedules_copied} jadwal
                                        khusus kelas disalin dari kelas bernama
                                        sama di tahun sebelumnya.
                                    </li>
                                )}
                                <li>
                                    Folder Google Drive siswa dipindahkan ke
                                    kelas barunya di latar belakang.
                                </li>
                                <li>
                                    Kartu yang sudah dicetak masih memuat kelas
                                    lama sampai dibuat ulang.
                                </li>
                            </ul>
                            <div className="flex flex-wrap gap-2">
                                {hasil.summary.failed + hasil.summary.rejected >
                                    0 && (
                                    <Button variant="outline" asChild>
                                        <a href={unduh(hasil.key, 'ditolak')}>
                                            <Download />
                                            Unduh baris gagal &amp; ditolak
                                        </a>
                                    </Button>
                                )}
                                {hasil.summary.missing > 0 && (
                                    <Button variant="outline" asChild>
                                        <a href={unduh(hasil.key, 'tidak-ada')}>
                                            <Download />
                                            Unduh siswa yang tidak ada di berkas
                                        </a>
                                    </Button>
                                )}
                            </div>
                            {hasil.failures.length > 0 && (
                                <div className="overflow-x-auto">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Baris</TableHead>
                                                <TableHead>Nama</TableHead>
                                                <TableHead>
                                                    Kelas Baru
                                                </TableHead>
                                                <TableHead>
                                                    Kenapa gagal
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {hasil.failures.map((row) => (
                                                <TableRow
                                                    key={`gagal-${row.row_number}`}
                                                >
                                                    <TableCell>
                                                        {row.row_number}
                                                    </TableCell>
                                                    <TableCell>
                                                        {row.student_name ??
                                                            '—'}
                                                    </TableCell>
                                                    <TableCell>
                                                        {row.to_classroom}
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
                )}

                {preview && (
                    <>
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant="secondary">
                                    {preview.filename}
                                </Badge>
                                <Badge variant="secondary">
                                    {preview.summary.promote} naik kelas
                                </Badge>
                                <Badge variant="secondary">
                                    {graduateRows.length} lulus
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
                                disabled={applying || jumlahDiterapkan === 0}
                            >
                                <ArrowUpRight />
                                {applying
                                    ? 'Menerapkan…'
                                    : `Terapkan untuk ${jumlahDiterapkan} Siswa`}
                            </Button>
                        </div>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <CheckCircle2 className="size-4" />
                                    Naik kelas ({promoteRows.length})
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
                                                            {row.nisn ||
                                                                `NIS ${row.nis ?? ''}`}
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

                        {graduateRows.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <GraduationCap className="size-4" />
                                        Lulus ({graduateRows.length})
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <p className="text-sm text-muted-foreground">
                                        Siswa berikut akan{' '}
                                        <strong>dinonaktifkan</strong>. Riwayat
                                        absensinya tetap ada, dan bisa
                                        diaktifkan lagi dari menu Siswa.
                                    </p>
                                    <div className="overflow-x-auto">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Baris</TableHead>
                                                    <TableHead>NISN</TableHead>
                                                    <TableHead>Nama</TableHead>
                                                    <TableHead>
                                                        Kelas Terakhir
                                                    </TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {graduateRows.map((row) => (
                                                    <TableRow
                                                        key={`lulus-${row.row_number}`}
                                                    >
                                                        <TableCell>
                                                            {row.row_number}
                                                        </TableCell>
                                                        <TableCell>
                                                            {row.nisn || '—'}
                                                        </TableCell>
                                                        <TableCell>
                                                            {row.student_name}
                                                        </TableCell>
                                                        <TableCell className="text-muted-foreground">
                                                            {row.from_classroom ??
                                                                '—'}
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

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
                                    apakah memang sengaja dilewati atau
                                    berkasnya kurang lengkap.
                                </p>
                                {missingRows.length > 0 && (
                                    <Button variant="outline" size="sm" asChild>
                                        <a
                                            href={unduh(
                                                preview.key,
                                                'tidak-ada',
                                            )}
                                        >
                                            <Download />
                                            Unduh daftar ini
                                        </a>
                                    </Button>
                                )}
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
                            <CardContent className="space-y-4">
                                {rejectRows.length > 0 && (
                                    <Button variant="outline" size="sm" asChild>
                                        <a href={unduh(preview.key, 'ditolak')}>
                                            <Download />
                                            Unduh baris ditolak
                                        </a>
                                    </Button>
                                )}
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
                                                    <TableHead>Nama</TableHead>
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
                                                            {row.nisn ||
                                                                row.nis ||
                                                                '—'}
                                                        </TableCell>
                                                        <TableCell>
                                                            {row.student_name ??
                                                                '—'}
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
