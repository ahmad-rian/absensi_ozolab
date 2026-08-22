import { Head, Link, router, usePage, WhenVisible } from '@inertiajs/react';
import {
    AlarmClock,
    ArrowLeft,
    CalendarCheck,
    CalendarDays,
    CreditCard,
    Download,
    HardDrive,
    Images,
    Loader2,
    MoonStar,
    Percent,
    Printer,
    RefreshCw,
    Search,
    User,
    UserCheck,
    UserX,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { refreshDrivePhoto as refreshDrivePhotoRoute } from '@/actions/App/Http/Controllers/Admin/SiswaController';
import { ATTENDANCE_SERIES, PRAYER_SERIES } from '@/components/student/daily-bar-chart';
import { PrayerMembershipCard } from '@/components/student/prayer-membership-card';
import { reportExports } from '@/components/student/range-bar';
import { StatsPanel, type TileSpec } from '@/components/student/stats-panel';
import { attendanceSlices, prayerSlices } from '@/components/student/status-pie';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { dashboard } from '@/routes';
import type { SchoolFeatureMap } from '@/types';
import type { AttendanceStats, PrayerStats, RangeFilters } from '@/types/student-stats';

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
    /** Nama berkas foto di Drive, dari form pendaftaran. Null untuk siswa lama. */
    photo_drive_filename: string | null;
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

type DrivePhotoFile = {
    file_id: string;
    name: string;
    view_url: string;
    download_url: string;
};

type DrivePhoto = {
    feature_enabled: boolean;
    /** null berarti sudah dicari tapi tidak ketemu — beda dari prop yang belum ada. */
    file: DrivePhotoFile | null;
    expected_file_name: string;
    expected_folder: string;
};

type PageProps = {
    student: Student;
    qrSvg: string;
    photoSheets: PhotoSheet[];
    photoSheetTemplates: PhotoSheetTemplate[];
    cards: GeneratedCard[];
    /** Ada kartu yang sedang dirender — `cards` sendiri hanya berisi yang selesai. */
    cardsProcessing: boolean;
    filters: RangeFilters;
    /** Ditunda — tab absensi paling sering dibuka, jadi dihangatkan lebih dulu. */
    attendance?: AttendanceStats;
    /** Opsional — hanya diambil saat tab jenisnya benar-benar dibuka. */
    prayerDhuha?: PrayerStats;
    prayerDzuhur?: PrayerStats;
    /** Opsional — menembak API Drive, jadi baru diminta saat tombolnya ditekan. */
    drivePhoto?: DrivePhoto;
};

const CARD_TYPES: { value: string; label: string }[] = [
    { value: 'osis', label: 'Kartu OSIS' },
    { value: 'perpustakaan', label: 'Kartu Perpustakaan' },
    { value: 'identitas', label: 'Kartu Identitas' },
];


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
    cardsProcessing,
    filters,
    attendance,
    prayerDhuha,
    prayerDzuhur,
    drivePhoto,
}: PageProps) {
    const { features } = usePage().props as unknown as { features?: Partial<SchoolFeatureMap> };

    // Tab jenis sholat yang dimatikan sekolah disembunyikan sepenuhnya, bukan
    // ditampilkan lalu kosong — flag-nya ada di prop non-defer supaya tidak
    // ada kedip panel kosong sebelum payload statistiknya tiba.
    const prayerTabs = [
        { value: 'dhuha', label: 'Sholat Dhuha', prop: 'prayerDhuha', feature: 'sholat_dhuha' as const },
        { value: 'dzuhur', label: 'Sholat Dzuhur', prop: 'prayerDzuhur', feature: 'sholat_dzuhur' as const },
    ].filter((entry) => features?.[entry.feature] !== false);

    const allowedTabs = ['profil', 'absensi', ...prayerTabs.map((entry) => entry.value)];

    const [template, setTemplate] = useState(photoSheetTemplates[0]?.value ?? '');
    const [caption, setCaption] = useState('');
    const [generating, setGenerating] = useState(false);
    const [drivePhotoLoading, setDrivePhotoLoading] = useState(false);
    const [regenerating, setRegenerating] = useState<'kartu' | 'pas-foto' | 'foto' | null>(null);
    const [tab, setTab] = useState(() => initialTab(allowedTabs));
    const [startDate, setStartDate] = useState(filters.start);
    const [endDate, setEndDate] = useState(filters.end);

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

    // Kartu yang sedang dirender tidak muncul di `cards` sampai selesai, jadi
    // tanpa polling ini tombolnya terasa tidak melakukan apa-apa.
    useEffect(() => {
        if (!cardsProcessing) {
            return;
        }

        let reloading = false;
        const interval = window.setInterval(() => {
            if (reloading) {
                return;
            }
            reloading = true;
            router.reload({
                only: ['cards', 'cardsProcessing'],
                onFinish: () => {
                    reloading = false;
                },
            });
        }, 3000);

        return () => window.clearInterval(interval);
    }, [cardsProcessing]);

    function handlePrint() {
        window.print();
    }

    /**
     * Generate ulang satu keluaran saja.
     *
     * Dipisah per keluaran karena merender kartu memanggil headless Chrome dan
     * mengambil foto memukul Drive: memperbaiki satu berkas tidak boleh
     * menjalankan semuanya di antrean yang dipakai bersama seluruh sekolah.
     */
    function regenerate(what: 'kartu' | 'pas-foto' | 'foto') {
        setRegenerating(what);
        router.post(
            `/admin/siswa/${student.id}/regenerate/${what}`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setRegenerating(null),
            },
        );
    }

    function loadDrivePhoto() {
        router.reload({
            only: ['drivePhoto'],
            onStart: () => setDrivePhotoLoading(true),
            onFinish: () => setDrivePhotoLoading(false),
        });
    }

    // Hasil pencarian ditahan di server selama beberapa jam, jadi mencari ulang
    // harus membuang cache-nya dulu — kalau tidak, foto yang baru diunggah ke
    // Drive tetap dilaporkan tidak ada.
    function researchDrivePhoto() {
        setDrivePhotoLoading(true);
        router.post(
            refreshDrivePhotoRoute.url(student.id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => loadDrivePhoto(),
                onError: () => setDrivePhotoLoading(false),
            },
        );
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
                        {prayerTabs.map((entry) => (
                            <TabsTrigger key={entry.value} value={entry.value}>
                                <MoonStar className="size-4" />
                                {entry.label}
                            </TabsTrigger>
                        ))}
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

                        {/* Pas foto asli di Drive — dicari saat diminta, tidak disimpan di DB */}
                        <Card className="print:hidden">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <HardDrive className="size-4" /> Pas Foto di Google Drive
                                </CardTitle>
                                <CardDescription>Buka atau unduh berkas aslinya tanpa mencari manual di Drive.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {drivePhotoLoading ? (
                                    <div className="space-y-2">
                                        <Skeleton className="h-4 w-3/4 animate-pulse" />
                                        <Skeleton className="h-9 w-full animate-pulse" />
                                    </div>
                                ) : !drivePhoto ? (
                                    <Button variant="outline" className="w-full" onClick={loadDrivePhoto}>
                                        <Search className="mr-2 size-4" />
                                        Cari di Drive
                                    </Button>
                                ) : !drivePhoto.feature_enabled ? (
                                    <p className="text-muted-foreground text-xs">Integrasi Google Drive dimatikan untuk sekolah ini.</p>
                                ) : drivePhoto.file ? (
                                    <>
                                        <p className="text-muted-foreground truncate text-xs" title={drivePhoto.file.name}>
                                            {drivePhoto.file.name}
                                        </p>
                                        <div className="flex flex-col gap-2">
                                            <Button variant="outline" className="w-full" asChild>
                                                <a href={drivePhoto.file.view_url} target="_blank" rel="noreferrer">
                                                    <HardDrive className="mr-2 size-4" />
                                                    Buka di Drive
                                                </a>
                                            </Button>
                                            <Button variant="outline" className="w-full" asChild>
                                                <a href={drivePhoto.file.download_url} target="_blank" rel="noreferrer">
                                                    <Download className="mr-2 size-4" />
                                                    Unduh Langsung
                                                </a>
                                            </Button>
                                        </div>
                                    </>
                                ) : (
                                    <>
                                        <p className="text-muted-foreground text-xs">
                                            Tidak ditemukan. Dicari berkas bernama{' '}
                                            <span className="text-foreground font-medium">{drivePhoto.expected_file_name}</span> di folder{' '}
                                            <span className="text-foreground font-medium">{drivePhoto.expected_folder}</span>.
                                        </p>
                                        <Button variant="outline" className="w-full" onClick={researchDrivePhoto}>
                                            <Search className="mr-2 size-4" />
                                            Cari Ulang
                                        </Button>
                                    </>
                                )}

                                {/* Nama berkas yang diketik saat mendaftar baru
                                    disimpan sejak migrasi kolom Drive; siswa lama
                                    belum punya, jadi tombolnya dimatikan dan
                                    alasannya ditulis, bukan menebak nama. */}
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    onClick={() => regenerate('foto')}
                                    disabled={regenerating !== null || !student.photo_drive_filename}
                                    title={
                                        student.photo_drive_filename
                                            ? `Ambil ulang ${student.photo_drive_filename} dari Drive`
                                            : 'Nama berkas foto di Drive tidak tersimpan untuk siswa ini.'
                                    }
                                >
                                    {regenerating === 'foto' ? (
                                        <Loader2 className="mr-2 size-4 animate-spin" />
                                    ) : (
                                        <RefreshCw className="mr-2 size-4" />
                                    )}
                                    Ambil Ulang Foto
                                </Button>
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
                                    {/* Lembar 4R bawaan, sama dengan yang keluar saat
                                        siswa mendaftar — tanpa memilih template lagi. */}
                                    <Button
                                        variant="outline"
                                        className="w-full"
                                        onClick={() => regenerate('pas-foto')}
                                        disabled={regenerating !== null || !student.photo_url}
                                    >
                                        {regenerating === 'pas-foto' ? (
                                            <Loader2 className="mr-2 size-4 animate-spin" />
                                        ) : (
                                            <RefreshCw className="mr-2 size-4" />
                                        )}
                                        Ulangi Lembar Bawaan (4R)
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

                                <Button
                                    variant="outline"
                                    className="mt-2 w-full print:hidden"
                                    onClick={() => regenerate('kartu')}
                                    disabled={regenerating !== null || cardsProcessing}
                                >
                                    {cardsProcessing ? (
                                        <Loader2 className="mr-2 size-4 animate-spin" />
                                    ) : (
                                        <RefreshCw className="mr-2 size-4" />
                                    )}
                                    {cardsProcessing ? 'Sedang dibuat…' : 'Generate Ulang Kartu'}
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
                    </TabsContent>

                    <TabsContent value="absensi">
                        <StatsPanel
                            range={filters.label}
                            startDate={startDate}
                            endDate={endDate}
                            onStartChange={setStartDate}
                            onEndChange={setEndDate}
                            onApply={applyRange}
                            exports={reportExports(student.id, 'absensi', filters.start, filters.end)}
                            tiles={attendanceTiles(attendance)}
                            daily={attendance?.daily}
                            dailySeries={ATTENDANCE_SERIES}
                            dailyTitle="Kehadiran Harian"
                            slices={attendanceSlices(attendance?.summary)}
                            pieTitle="Distribusi Status"
                            weekday={attendance?.by_weekday}
                            weekdayTitle="Pola per Hari"
                            streaks={attendance?.streaks}
                            monthly={attendance?.monthly}
                            heatmap={attendance?.heatmap}
                            heatmapTitle="Kalender Kehadiran"
                            comparison={attendance?.comparison}
                            studentRate={attendance?.summary.rate}
                            history={attendance?.recent}
                            historyTitle="Riwayat Absensi"
                            historyEmpty="Belum ada catatan absensi pada rentang ini."
                            historyShowType
                        />
                    </TabsContent>

                    {prayerTabs.map((entry) => (
                        <TabsContent key={entry.value} value={entry.value}>
                            {/* Key pada rentang: applyRange memakai preserveState sehingga
                                komponen tidak remount, dan WhenVisible yang hanya menembak
                                sekali akan macet di Skeleton setelah tombol Terapkan. */}
                            <WhenVisible
                                key={`${entry.value}-${filters.start}-${filters.end}`}
                                data={entry.prop}
                                fallback={<PanelSkeleton />}
                            >
                                <PrayerPanel
                                    studentId={student.id}
                                    jenis={entry.value}
                                    stats={entry.value === 'dhuha' ? prayerDhuha : prayerDzuhur}
                                    filters={filters}
                                    startDate={startDate}
                                    endDate={endDate}
                                    onStartChange={setStartDate}
                                    onEndChange={setEndDate}
                                    onApply={applyRange}
                                />
                            </WhenVisible>
                        </TabsContent>
                    ))}
                </Tabs>
            </div>
        </>
    );
}

/** `sholat` adalah nama tab lama sebelum dipecah; tautan lama tetap mendarat
 *  di tempat yang masuk akal alih-alih diam-diam kembali ke Profil. */
const TAB_ALIASES: Record<string, string> = { sholat: 'dzuhur' };

function initialTab(allowed: string[]): string {
    if (typeof window === 'undefined') {
        return 'profil';
    }

    const raw = new URL(window.location.href).searchParams.get('tab') ?? '';
    const requested = TAB_ALIASES[raw] ?? raw;

    return allowed.includes(requested) ? requested : 'profil';
}

function attendanceTiles(attendance?: AttendanceStats): TileSpec[] {
    return [
        { label: 'Hadir', value: attendance?.summary.hadir ?? '—', icon: UserCheck, tone: 'green' },
        { label: 'Terlambat', value: attendance?.summary.terlambat ?? '—', icon: AlarmClock, tone: 'amber' },
        {
            label: 'Tidak Hadir',
            value: attendance
                ? attendance.summary.alpa +
                  attendance.summary.izin +
                  attendance.summary.sakit +
                  attendance.summary.tanpa_keterangan
                : '—',
            icon: UserX,
            tone: 'red',
            hint: attendance
                ? `Izin ${attendance.summary.izin} · Sakit ${attendance.summary.sakit} · Alpa ${attendance.summary.alpa}`
                : undefined,
        },
        {
            label: 'Kehadiran',
            value: attendance?.summary.rate ?? '—',
            suffix: '%',
            icon: Percent,
            tone: 'blue',
            hint: attendance
                ? `Tepat waktu ${attendance.summary.punctual_rate}% · Rata-rata masuk ${attendance.punctuality.avg_check_in ?? '-'}`
                : undefined,
        },
    ];
}

function PrayerPanel({
    studentId,
    jenis,
    stats,
    filters,
    startDate,
    endDate,
    onStartChange,
    onEndChange,
    onApply,
}: {
    studentId: string;
    jenis: string;
    stats?: PrayerStats;
    filters: RangeFilters;
    startDate: string;
    endDate: string;
    onStartChange: (value: string) => void;
    onEndChange: (value: string) => void;
    onApply: () => void;
}) {
    const detail = stats?.types[0];

    // Payload sudah tiba tapi jenis ini tidak aktif: tampilkan sebabnya, bukan
    // skeleton abadi yang terbaca seperti request menggantung.
    if (stats && !detail) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Absen sholat belum diaktifkan</CardTitle>
                    <CardDescription>
                        Nyalakan dulu di Pengaturan → tab Absen Sholat, lengkap dengan jam mulai dan selesai.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Button variant="outline" asChild>
                        <Link href="/admin/pengaturan?tab=sholat">Buka Pengaturan</Link>
                    </Button>
                </CardContent>
            </Card>
        );
    }

    const tiles: TileSpec[] = [
        { label: 'Ikut Sholat', value: detail?.summary.hadir ?? '—', icon: MoonStar, tone: 'green' },
        { label: 'Tidak Ikut', value: detail?.summary.tidak_hadir ?? '—', icon: UserX, tone: 'red' },
        { label: 'Hari Efektif', value: detail?.summary.effective_days ?? '—', icon: CalendarDays, tone: 'slate' },
        {
            label: 'Kehadiran',
            value: detail?.summary.rate ?? '—',
            suffix: '%',
            icon: Percent,
            tone: 'blue',
            hint: detail ? `Jendela ${detail.window}` : undefined,
        },
    ];

    return (
        <StatsPanel
            range={filters.label}
            startDate={startDate}
            endDate={endDate}
            onStartChange={onStartChange}
            onEndChange={onEndChange}
            onApply={onApply}
            exports={reportExports(studentId, 'sholat', filters.start, filters.end, jenis)}
            tiles={tiles}
            daily={detail?.daily}
            dailySeries={PRAYER_SERIES}
            dailyTitle="Kehadiran Sholat Harian"
            slices={prayerSlices(detail?.summary)}
            pieTitle="Proporsi Kehadiran"
            weekday={detail?.by_weekday}
            weekdayTitle="Pola per Hari"
            streaks={detail?.streaks}
            heatmap={detail?.heatmap}
            heatmapTitle="Kalender Sholat"
            history={detail?.recent}
            historyTitle="Riwayat Absen Sholat"
            historyEmpty="Belum ada catatan absen sholat pada rentang ini."
        >
            {stats && <PrayerMembershipCard studentId={studentId} prayer={stats} />}
        </StatsPanel>
    );
}

function PanelSkeleton() {
    return (
        <div className="space-y-6">
            <Skeleton className="h-24 w-full" />
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {[0, 1, 2, 3].map((index) => (
                    <Skeleton key={index} className="h-20 w-full" />
                ))}
            </div>
            <Skeleton className="h-[360px] w-full" />
        </div>
    );
}

SiswaShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Siswa', href: '/admin/siswa' },
        { title: 'Detail Siswa', href: '#' },
    ],
};
