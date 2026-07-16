import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Download, HardDrive, Images, Loader2, Printer, User } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { dashboard } from '@/routes';

type Classroom = {
    id: string;
    name: string;
};

type ParentUser = {
    id: string;
    name: string;
};

type ParentProfile = {
    id: string;
    user: ParentUser | null;
    relation_label?: string;
    whatsapp_number?: string | null;
    email?: string | null;
};

type Student = {
    id: string;
    nis: string | null;
    nisn: string | null;
    no_absen: string | null;
    full_name: string;
    gender: string;
    religion: string | null;
    religion_label: string | null;
    is_active: boolean;
    birth_place: string | null;
    birth_date: string | null;
    address: string | null;
    photo_url: string | null;
    parent_name: string | null;
    parent_phone: string | null;
    classroom: Classroom | null;
    parent_profile: ParentProfile | null;
};

type PhotoSheet = {
    id: string;
    template?: string;
    status: string;
    file_url: string | null;
    drive_url: string | null;
    created_at: string;
};

type PhotoSheetTemplate = {
    value: string;
    label: string;
};

type PageProps = {
    student: Student;
    qrSvg: string;
    photoSheets: PhotoSheet[];
    photoSheetTemplates: PhotoSheetTemplate[];
};

const sheetStatusConfig: Record<string, { label: string; className: string }> = {
    completed: {
        label: 'Selesai',
        className: 'border-green-200 bg-green-100 text-green-800 dark:border-green-800 dark:bg-green-900 dark:text-green-300',
    },
    failed: {
        label: 'Gagal',
        className: 'border-red-200 bg-red-100 text-red-800 dark:border-red-800 dark:bg-red-900 dark:text-red-300',
    },
    processing: {
        label: 'Proses',
        className: 'border-amber-200 bg-amber-100 text-amber-800 dark:border-amber-800 dark:bg-amber-900 dark:text-amber-300',
    },
};

function genderLabel(gender: string): string {
    return gender === 'LAKI_LAKI' ? 'Laki-laki' : 'Perempuan';
}

function formatDate(date: string | null): string {
    if (!date) {
        return '-';
    }
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="grid grid-cols-3 gap-2 border-b py-2.5 last:border-b-0">
            <dt className="text-muted-foreground text-sm font-medium">{label}</dt>
            <dd className="col-span-2 text-sm">{value || '-'}</dd>
        </div>
    );
}

export default function SiswaShow({ student, qrSvg, photoSheets, photoSheetTemplates }: PageProps) {
    const [template, setTemplate] = useState(photoSheetTemplates[0]?.value ?? '');
    const [caption, setCaption] = useState('');
    const [generating, setGenerating] = useState(false);

    function handlePrint() {
        window.print();
    }

    function handleGenerateSheet() {
        if (!template) {
            return;
        }
        router.post(
            `/admin/siswa/${student.id}/photo-sheet`,
            { template, caption },
            {
                preserveScroll: true,
                onStart: () => setGenerating(true),
                onFinish: () => setGenerating(false),
            },
        );
    }

    return (
        <>
            <Head title={`Detail Siswa - ${student.full_name}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Button variant="outline" size="icon" asChild>
                            <Link href="/admin/siswa">
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">{student.full_name}</h1>
                            <p className="text-muted-foreground text-sm">Detail informasi siswa</p>
                        </div>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={`/admin/siswa/${student.id}/edit`}>Edit Data</Link>
                    </Button>
                </div>

                {/* Content */}
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Left: Student Info */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Photo + Name Header */}
                        <Card>
                            <CardContent className="flex items-center gap-5 p-5">
                                {student.photo_url ? (
                                    <img
                                        src={student.photo_url}
                                        alt={student.full_name}
                                        className="size-24 shrink-0 rounded-xl border-2 border-blue-200 object-cover shadow-md"
                                    />
                                ) : (
                                    <div className="flex size-24 shrink-0 items-center justify-center rounded-xl border-2 border-zinc-200 bg-zinc-100 dark:bg-zinc-800">
                                        <User className="size-10 text-zinc-400" />
                                    </div>
                                )}
                                <div>
                                    <h2 className="text-xl font-bold">{student.full_name}</h2>
                                    <p className="text-muted-foreground text-sm">
                                        {student.nis && `NIS: ${student.nis}`}
                                        {student.nisn && ` · NISN: ${student.nisn}`}
                                    </p>
                                    <p className="text-muted-foreground text-sm">
                                        {student.classroom?.name}
                                        {student.no_absen && ` · No. Absen: ${student.no_absen}`}
                                    </p>
                                    <Badge variant={student.is_active ? 'default' : 'secondary'} className="mt-2">
                                        {student.is_active ? 'Aktif' : 'Nonaktif'}
                                    </Badge>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Informasi Siswa</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl>
                                    <InfoRow label="NIS" value={student.nis} />
                                    <InfoRow label="NISN" value={student.nisn} />
                                    <InfoRow label="Nama Lengkap" value={student.full_name} />
                                    <InfoRow label="Kelas" value={student.classroom?.name} />
                                    <InfoRow label="Jenis Kelamin" value={genderLabel(student.gender)} />
                                    <InfoRow label="Agama" value={student.religion_label} />
                                    <InfoRow
                                        label="Tempat, Tanggal Lahir"
                                        value={
                                            student.birth_place || student.birth_date
                                                ? `${student.birth_place ?? '-'}, ${formatDate(student.birth_date)}`
                                                : '-'
                                        }
                                    />
                                    <InfoRow label="Alamat" value={student.address} />
                                    <InfoRow label="Orang Tua / Wali" value={student.parent_profile?.user?.name ?? student.parent_name} />
                                    <InfoRow label="Hubungan" value={student.parent_profile?.relation_label} />
                                    <InfoRow label="No. WhatsApp" value={student.parent_profile?.whatsapp_number ?? student.parent_phone} />
                                    <InfoRow
                                        label="Email Notifikasi"
                                        value={
                                            student.parent_profile?.email && !student.parent_profile.email.endsWith('@internal.app')
                                                ? student.parent_profile.email
                                                : 'Belum diisi'
                                        }
                                    />
                                    <InfoRow
                                        label="Status"
                                        value={
                                            <Badge variant={student.is_active ? 'default' : 'secondary'}>
                                                {student.is_active ? 'Aktif' : 'Nonaktif'}
                                            </Badge>
                                        }
                                    />
                                </dl>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right: QR Code */}
                    <div className="lg:col-span-1 space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>QR Code Absensi</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-col items-center gap-4">
                                    {/* QR Frame — this div is the only thing visible when printing */}
                                    <div className="print-area rounded-xl border-2 border-dashed border-gray-300 bg-white p-6 print:border-solid print:border-gray-800">
                                        <div
                                            className="mx-auto w-full max-w-[250px] [&>svg]:h-auto [&>svg]:w-full"
                                            dangerouslySetInnerHTML={{ __html: qrSvg }}
                                        />
                                        <div className="mt-3 text-center">
                                            <p className="text-sm font-semibold text-gray-900">{student.full_name}</p>
                                            <p className="text-xs text-gray-500">{student.nis ?? 'Tanpa NIS'}</p>
                                        </div>
                                    </div>

                                    {/* Actions */}
                                    <div className="flex w-full flex-col gap-2 print:hidden">
                                        <Button variant="outline" className="w-full" asChild>
                                            <a href={`/admin/siswa/${student.id}/qr`} download>
                                                <Download className="mr-2 size-4" />
                                                Download QR
                                            </a>
                                        </Button>
                                        <Button variant="outline" className="w-full" onClick={handlePrint}>
                                            <Printer className="mr-2 size-4" />
                                            Cetak Kartu
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Cetak Pas Foto */}
                        <Card className="print:hidden">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Images className="size-4" /> Cetak Pas Foto
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-col gap-4">
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="sheet-template">Template</Label>
                                        <Select value={template} onValueChange={setTemplate}>
                                            <SelectTrigger id="sheet-template" className="w-full">
                                                <SelectValue placeholder="Pilih template" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {photoSheetTemplates.map((t) => (
                                                    <SelectItem key={t.value} value={t.value}>
                                                        {t.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="sheet-caption">Keterangan (opsional)</Label>
                                        <Input
                                            id="sheet-caption"
                                            value={caption}
                                            onChange={(e) => setCaption(e.target.value)}
                                            placeholder="Contoh: Nama & Kelas"
                                            maxLength={255}
                                        />
                                    </div>
                                    <Button className="w-full" onClick={handleGenerateSheet} disabled={generating || !student.photo_url || !template}>
                                        {generating ? <Loader2 className="mr-2 size-4 animate-spin" /> : <Images className="mr-2 size-4" />}
                                        Generate
                                    </Button>
                                    {!student.photo_url && (
                                        <p className="text-muted-foreground text-xs">Siswa belum memiliki foto. Unggah foto terlebih dahulu.</p>
                                    )}

                                    {photoSheets.length > 0 && (
                                        <div className="mt-2 flex flex-col gap-2 border-t pt-3">
                                            <p className="text-muted-foreground text-xs font-medium">Riwayat</p>
                                            {photoSheets.map((sheet) => {
                                                const status = sheetStatusConfig[sheet.status] ?? { label: sheet.status, className: '' };
                                                const url = sheet.drive_url ?? sheet.file_url;
                                                return (
                                                    <div key={sheet.id} className="flex items-center justify-between gap-2 text-sm">
                                                        <div className="flex items-center gap-2">
                                                            <Badge variant="outline" className={`gap-1 ${status.className}`}>
                                                                {status.label}
                                                            </Badge>
                                                            <span className="text-muted-foreground text-xs">{sheet.created_at}</span>
                                                        </div>
                                                        {url ? (
                                                            <Button variant="ghost" size="icon" asChild title="Buka berkas">
                                                                <a href={url} target="_blank" rel="noreferrer">
                                                                    {sheet.drive_url ? <HardDrive className="size-4" /> : <Download className="size-4" />}
                                                                </a>
                                                            </Button>
                                                        ) : (
                                                            <span className="text-muted-foreground text-xs">-</span>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

SiswaShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Siswa', href: '/admin/siswa' },
        { title: 'Detail Siswa', href: '#' },
    ],
};
