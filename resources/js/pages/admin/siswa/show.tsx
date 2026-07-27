import { Head, Link, router } from '@inertiajs/react';
import {
    AlarmClock,
    ArrowLeft,
    CalendarCheck,
    CalendarDays,
    CreditCard,
    Download,
    FileSpreadsheet,
    FileText,
    HardDrive,
    Images,
    Loader2,
    MoonStar,
    Percent,
    Printer,
    User,
    UserCheck,
    UserX,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { AttendanceDailyChart, type DailyPoint } from '@/components/student/attendance-daily-chart';
import { PrayerDailyChart, type PrayerDailyPoint } from '@/components/student/prayer-daily-chart';
import { StatTile } from '@/components/student/stat-tile';
import { StatusPie, type AttendanceSummary } from '@/components/student/status-pie';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
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

type GeneratedCard = {
    id: string;
    layout_type: string;
    layout_name: string;
    drive_url: string | null;
    file_url: string | null;
    created_at: string;
};

type AttendanceRow = {
    id: string;
    date: string;
    type: string;
    type_label: string;
    status: string;
    status_label: string;
    time: string | null;
    device_id: string | null;
};

type PrayerRow = {
    id: string;
    date: string;
    status: string;
    status_label: string;
    time: string | null;
    device_id: string | null;
};

type AttendancePayload = {
    summary: AttendanceSummary;
    daily: DailyPoint[];
    recent: AttendanceRow[];
};

type PrayerPayload = {
    enabled: boolean;
    covered: boolean;
    opt_in: boolean | null;
    school_includes_all: boolean;
    religion_label: string | null;
    window: string | null;
    summary: { hadir: number; tidak_hadir: number; effective_days: number; rate: number };
    daily: PrayerDailyPoint[];
    recent: PrayerRow[];
};

type PageProps = {
    student: Student;
    qrSvg: string;
    photoSheets: PhotoSheet[];
    photoSheetTemplates: PhotoSheetTemplate[];
    cards: GeneratedCard[];
    filters: { start: string; end: string };
    attendance?: AttendancePayload;
    prayer?: PrayerPayload;
};

const CARD_TYPES: { value: string; label: string }[] = [
    { value: 'osis', label: 'Kartu OSIS' },
    { value: 'perpustakaan', label: 'Kartu Perpustakaan' },
    { value: 'identitas', label: 'Kartu Identitas' },
];

const statusBadgeClass: Record<string, string> = {
    HADIR: 'border-green-200 bg-green-100 text-green-800 dark:border-green-800 dark:bg-green-900/40 dark:text-green-300',
    TERLAMBAT: 'border-amber-200 bg-amber-100 text-amber-800 dark:border-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    IZIN: 'border-blue-200 bg-blue-100 text-blue-800 dark:border-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    SAKIT: 'border-purple-200 bg-purple-100 text-purple-800 dark:border-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
    ALPA: 'border-red-200 bg-red-100 text-red-800 dark:border-red-800 dark:bg-red-900/40 dark:text-red-300',
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

export default function SiswaShow({
    student,
    qrSvg,
    photoSheets,
    photoSheetTemplates,
    cards,
    filters,
    attendance,
    prayer,
}: PageProps) {
    const [template, setTemplate] = useState(photoSheetTemplates[0]?.value ?? '');
    const [caption, setCaption] = useState('');
    const [generating, setGenerating] = useState(false);
    const [tab, setTab] = useState(() => initialTab());
    const [startDate, setStartDate] = useState(filters.start);
    const [endDate, setEndDate] = useState(filters.end);

    const rangeLabel = `${filters.start} s/d ${filters.end}`;
    const exportQuery = `start_date=${filters.start}&end_date=${filters.end}`;

    // Tab disimpan di query string supaya refresh dan tombol back tidak
    // melompat balik ke Profil.
    function changeTab(value: string) {
        setTab(value);
        const url = new URL(window.location.href);
        url.searchParams.set('tab', value);
        window.history.replaceState({}, '', url.toString());
    }

    function applyRange() {
        router.get(
            `/admin/siswa/${student.id}`,
            { tab, start_date: startDate, end_date: endDate },
            { preserveState: true, preserveScroll: true },
        );
    }

    const hasProcessingSheet = photoSheets.some((sheet) => sheet.status === 'processing');

    useEffect(() => {
        if (!hasProcessingSheet) {
            return;
        }

        let reloading = false;
        const interval = window.setInterval(() => {
            if (reloading) {
                return;
            }
            reloading = true;
            router.reload({
                only: ['photoSheets'],
                onFinish: () => {
                    reloading = false;
                },
            });
        }, 3000);

        return () => window.clearInterval(interval);
    }, [hasProcessingSheet]);

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

                <Tabs value={tab} onValueChange={changeTab}>
                    <TabsList>
                        <TabsTrigger value="profil">
                            <User className="size-4" />
                            Profil
                        </TabsTrigger>
                        <TabsTrigger value="absensi">
                            <CalendarCheck className="size-4" />
                            Absensi Sekolah
                        </TabsTrigger>
                        <TabsTrigger value="sholat">
                            <MoonStar className="size-4" />
                            Absen Sholat
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="profil">
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
                                            <div className="flex items-center justify-between">
                                                <p className="text-muted-foreground text-xs font-medium">Riwayat</p>
                                                {hasProcessingSheet && (
                                                    <span className="text-muted-foreground inline-flex items-center gap-1 text-xs">
                                                        <Loader2 className="size-3 animate-spin" /> memperbarui…
                                                    </span>
                                                )}
                                            </div>
                                            {photoSheets.map((sheet) => {
                                                const status = sheetStatusConfig[sheet.status] ?? { label: sheet.status, className: '' };
                                                const url = sheet.drive_url ?? sheet.file_url;
                                                return (
                                                    <div key={sheet.id} className="flex items-center justify-between gap-2 text-sm">
                                                        <div className="flex items-center gap-2">
                                                            <Badge variant="outline" className={`gap-1 ${status.className}`}>
                                                                {sheet.status === 'processing' && <Loader2 className="size-3 animate-spin" />}
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

                        {/* Kartu Digital — tautan langsung ke berkas di Google Drive */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <CreditCard className="size-5" />
                                    Kartu Digital
                                </CardTitle>
                                <CardDescription>Buka langsung kartunya, tanpa perlu mencari di Google Drive.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                {CARD_TYPES.map((type) => {
                                    const card = cards.find((item) => item.layout_type === type.value);
                                    const url = card?.drive_url ?? card?.file_url ?? null;

                                    return (
                                        <div key={type.value} className="flex items-center justify-between gap-2 border-b py-2 last:border-b-0">
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">{card?.layout_name ?? type.label}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    {card ? card.created_at : 'Belum digenerate'}
                                                </p>
                                            </div>
                                            {url ? (
                                                <Button variant="outline" size="sm" asChild>
                                                    <a href={url} target="_blank" rel="noreferrer">
                                                        {card?.drive_url ? <HardDrive className="mr-1.5 size-4" /> : <Download className="mr-1.5 size-4" />}
                                                        Buka
                                                    </a>
                                                </Button>
                                            ) : (
                                                <Button variant="outline" size="sm" disabled>
                                                    Buka
                                                </Button>
                                            )}
                                        </div>
                                    );
                                })}
                            </CardContent>
                        </Card>
                    </div>
                </div>
                    </TabsContent>

                    <TabsContent value="absensi" className="space-y-6">
                        <RangeBar
                            startDate={startDate}
                            endDate={endDate}
                            onStartChange={setStartDate}
                            onEndChange={setEndDate}
                            onApply={applyRange}
                            csvHref={`/admin/siswa/${student.id}/laporan/absensi/csv?${exportQuery}`}
                            pdfHref={`/admin/siswa/${student.id}/laporan/absensi/pdf?${exportQuery}`}
                        />

                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <StatTile label="Hadir" value={attendance?.summary.hadir ?? '—'} icon={UserCheck} tone="green" />
                            <StatTile label="Terlambat" value={attendance?.summary.terlambat ?? '—'} icon={AlarmClock} tone="amber" />
                            <StatTile
                                label="Tidak Hadir"
                                value={
                                    attendance
                                        ? attendance.summary.alpa + attendance.summary.izin + attendance.summary.sakit + attendance.summary.tanpa_keterangan
                                        : '—'
                                }
                                icon={UserX}
                                tone="red"
                                hint={
                                    attendance
                                        ? `Izin ${attendance.summary.izin} · Sakit ${attendance.summary.sakit} · Alpa ${attendance.summary.alpa}`
                                        : undefined
                                }
                            />
                            <StatTile
                                label="Kehadiran"
                                value={attendance?.summary.rate ?? '—'}
                                suffix="%"
                                icon={Percent}
                                tone="blue"
                                hint={attendance ? `${attendance.summary.effective_days} hari efektif` : undefined}
                            />
                        </div>

                        <div className="grid gap-6 lg:grid-cols-2">
                            <AttendanceDailyChart data={attendance?.daily} range={rangeLabel} />
                            <StatusPie summary={attendance?.summary} />
                        </div>

                        <Card>
                            <CardHeader>
                                <CardTitle>Riwayat Absensi</CardTitle>
                                <CardDescription>30 catatan terakhir pada rentang ini</CardDescription>
                            </CardHeader>
                            <CardContent className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Tanggal</TableHead>
                                            <TableHead>Jenis</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Jam</TableHead>
                                            <TableHead>Perangkat</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {!attendance ? (
                                            <TableRow>
                                                <TableCell colSpan={5} className="text-muted-foreground py-8 text-center">
                                                    Memuat…
                                                </TableCell>
                                            </TableRow>
                                        ) : attendance.recent.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={5} className="text-muted-foreground py-8 text-center">
                                                    Belum ada catatan absensi pada rentang ini.
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            attendance.recent.map((row) => (
                                                <TableRow key={row.id}>
                                                    <TableCell className="font-medium">{row.date}</TableCell>
                                                    <TableCell>{row.type_label}</TableCell>
                                                    <TableCell>
                                                        <Badge variant="outline" className={statusBadgeClass[row.status] ?? ''}>
                                                            {row.status_label}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="font-mono">{row.time ?? '-'}</TableCell>
                                                    <TableCell className="text-muted-foreground text-xs">{row.device_id ?? '-'}</TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="sholat" className="space-y-6">
                        {prayer && !prayer.enabled ? (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Absen sholat belum diaktifkan</CardTitle>
                                    <CardDescription>
                                        Nyalakan dulu di Pengaturan Sekolah, lengkap dengan jam mulai dan selesai.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Button variant="outline" asChild>
                                        <Link href="/admin/pengaturan">Buka Pengaturan</Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        ) : (
                            <>
                                <RangeBar
                                    startDate={startDate}
                                    endDate={endDate}
                                    onStartChange={setStartDate}
                                    onEndChange={setEndDate}
                                    onApply={applyRange}
                                    csvHref={`/admin/siswa/${student.id}/laporan/sholat/csv?${exportQuery}`}
                                    pdfHref={`/admin/siswa/${student.id}/laporan/sholat/pdf?${exportQuery}`}
                                />

                                {prayer && <PrayerMembershipCard studentId={student.id} prayer={prayer} />}

                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <StatTile label="Ikut Sholat" value={prayer?.summary.hadir ?? '—'} icon={MoonStar} tone="green" />
                                    <StatTile label="Tidak Ikut" value={prayer?.summary.tidak_hadir ?? '—'} icon={UserX} tone="red" />
                                    <StatTile label="Hari Efektif" value={prayer?.summary.effective_days ?? '—'} icon={CalendarDays} tone="slate" />
                                    <StatTile
                                        label="Kehadiran"
                                        value={prayer?.summary.rate ?? '—'}
                                        suffix="%"
                                        icon={Percent}
                                        tone="blue"
                                        hint={prayer?.window ? `Jendela ${prayer.window}` : undefined}
                                    />
                                </div>

                                <PrayerDailyChart data={prayer?.daily} range={rangeLabel} />

                                <Card>
                                    <CardHeader>
                                        <CardTitle>Riwayat Absen Sholat</CardTitle>
                                        <CardDescription>30 catatan terakhir pada rentang ini</CardDescription>
                                    </CardHeader>
                                    <CardContent className="overflow-x-auto">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Tanggal</TableHead>
                                                    <TableHead>Status</TableHead>
                                                    <TableHead>Jam</TableHead>
                                                    <TableHead>Perangkat</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {!prayer ? (
                                                    <TableRow>
                                                        <TableCell colSpan={4} className="text-muted-foreground py-8 text-center">
                                                            Memuat…
                                                        </TableCell>
                                                    </TableRow>
                                                ) : prayer.recent.length === 0 ? (
                                                    <TableRow>
                                                        <TableCell colSpan={4} className="text-muted-foreground py-8 text-center">
                                                            Belum ada catatan absen sholat pada rentang ini.
                                                        </TableCell>
                                                    </TableRow>
                                                ) : (
                                                    prayer.recent.map((row) => (
                                                        <TableRow key={row.id}>
                                                            <TableCell className="font-medium">{row.date}</TableCell>
                                                            <TableCell>
                                                                <Badge variant="outline" className={statusBadgeClass[row.status] ?? ''}>
                                                                    {row.status_label}
                                                                </Badge>
                                                            </TableCell>
                                                            <TableCell className="font-mono">{row.time ?? '-'}</TableCell>
                                                            <TableCell className="text-muted-foreground text-xs">{row.device_id ?? '-'}</TableCell>
                                                        </TableRow>
                                                    ))
                                                )}
                                            </TableBody>
                                        </Table>
                                    </CardContent>
                                </Card>
                            </>
                        )}
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}

function initialTab(): string {
    if (typeof window === 'undefined') {
        return 'profil';
    }

    const requested = new URL(window.location.href).searchParams.get('tab');

    return requested && ['profil', 'absensi', 'sholat'].includes(requested) ? requested : 'profil';
}

function PrayerMembershipCard({ studentId, prayer }: { studentId: string; prayer: PrayerPayload }) {
    const schoolRule = prayer.school_includes_all ? 'semua siswa ikut' : 'hanya siswa beragama Islam';
    const overridden = prayer.opt_in !== null;

    function setOptIn(value: boolean | null) {
        router.patch(
            `/admin/siswa/${studentId}/prayer-opt-in`,
            { prayer_opt_in: value },
            { preserveScroll: true, preserveState: true },
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <MoonStar className="size-5" />
                    Kepesertaan Sholat
                </CardTitle>
                <CardDescription>
                    Agama: {prayer.religion_label ?? '-'} · Aturan sekolah: {schoolRule}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                <label className="flex items-center gap-3 text-sm">
                    <Checkbox
                        checked={prayer.covered}
                        onCheckedChange={(checked) => setOptIn(Boolean(checked))}
                    />
                    Ikutkan siswa ini di absen sholat
                </label>

                <div className="flex flex-wrap items-center gap-3">
                    <Badge variant={prayer.covered ? 'default' : 'secondary'}>
                        {prayer.covered ? 'Ikut absen sholat' : 'Tidak ikut'}
                    </Badge>
                    <span className="text-muted-foreground text-xs">
                        {overridden ? 'Diatur khusus untuk siswa ini' : 'Mengikuti aturan sekolah'}
                    </span>
                    {overridden && (
                        <Button variant="ghost" size="sm" className="h-7 text-xs" onClick={() => setOptIn(null)}>
                            Kembalikan ke aturan sekolah
                        </Button>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function RangeBar({
    startDate,
    endDate,
    onStartChange,
    onEndChange,
    onApply,
    csvHref,
    pdfHref,
}: {
    startDate: string;
    endDate: string;
    onStartChange: (value: string) => void;
    onEndChange: (value: string) => void;
    onApply: () => void;
    csvHref: string;
    pdfHref: string;
}) {
    return (
        <Card>
            <CardContent className="flex flex-col gap-4 p-4 lg:flex-row lg:items-end lg:justify-between">
                <div className="grid flex-1 gap-3 sm:grid-cols-2 lg:max-w-md">
                    <div className="grid gap-1.5">
                        <Label htmlFor="start_date" className="text-xs">Dari Tanggal</Label>
                        <Input id="start_date" type="date" value={startDate} onChange={(e) => onStartChange(e.target.value)} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="end_date" className="text-xs">Sampai Tanggal</Label>
                        <Input id="end_date" type="date" value={endDate} onChange={(e) => onEndChange(e.target.value)} />
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button onClick={onApply}>Terapkan</Button>
                    <Button variant="outline" asChild>
                        <a href={csvHref}>
                            <FileSpreadsheet className="mr-1.5 size-4" />
                            CSV
                        </a>
                    </Button>
                    <Button variant="outline" asChild>
                        <a href={pdfHref}>
                            <FileText className="mr-1.5 size-4" />
                            PDF
                        </a>
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

SiswaShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Siswa', href: '/admin/siswa' },
        { title: 'Detail Siswa', href: '#' },
    ],
};
