import { Head, Link, router } from '@inertiajs/react';
import { Building2, Edit, Eye, GraduationCap, HardDrive, Search, Users } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';

type SchoolOption = { id: string; name: string };

type SummaryRow = {
    id: string;
    name: string;
    is_active: boolean;
    students_count: number;
    classrooms_count: number;
    hadir: number;
    terlambat: number;
};

type StudentRow = {
    id: string;
    full_name: string;
    nis: string | null;
    nisn: string | null;
    classroom: string | null;
    school: string | null;
    school_id: string;
    is_active: boolean;
};

type AttendanceRow = {
    id: string;
    name: string;
    hadir: number;
    terlambat: number;
    izin: number;
    sakit: number;
    alpa: number;
    total: number;
};

type CardRow = {
    id: string;
    school: string | null;
    student: string | null;
    type: string;
    status: string;
    drive_url: string | null;
    file_url: string | null;
    created_at: string;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

type PageProps = {
    tab: string;
    filters: { search: string; school_id: string; start: string; end: string };
    schools: SchoolOption[];
    totals: { schools: number; students: number; active_students: number };
    summary: SummaryRow[] | null;
    students: Paginated<StudentRow> | null;
    attendance: AttendanceRow[] | null;
    cards: Paginated<CardRow> | null;
};

const TABS = [
    { value: 'ringkasan', label: 'Ringkasan' },
    { value: 'siswa', label: 'Data Siswa' },
    { value: 'absensi', label: 'Absensi' },
    { value: 'kartu', label: 'Kartu & Pas Foto' },
];

export default function SemuaSekolahIndex({ tab, filters, schools, totals, summary, students, attendance, cards }: PageProps) {
    const [search, setSearch] = useState(filters.search);
    const [start, setStart] = useState(filters.start);
    const [end, setEnd] = useState(filters.end);

    function go(next: Partial<PageProps['filters'] & { tab: string }>) {
        const merged = { tab, ...filters, ...next };

        router.get(
            '/admin/semua-sekolah',
            {
                tab: merged.tab,
                search: merged.search || undefined,
                school_id: merged.school_id || undefined,
                start: merged.start || undefined,
                end: merged.end || undefined,
            },
            { preserveState: true, replace: true },
        );
    }

    /**
     * Memindahkan sekolah aktif di sesi ke sekolah siswa ini, lalu membuka
     * halamannya. Perpindahannya diumumkan lewat toast dari server — sesudah ini
     * pemilih sekolah di kepala halaman ikut berganti, dan itu memang disengaja.
     */
    function bukaSiswa(id: string, tujuan: 'show' | 'edit') {
        router.post(`/admin/siswa/${id}/buka`, { tujuan });
    }

    return (
        <>
            <Head title="Semua Sekolah" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Semua Sekolah</h1>
                    <p className="text-muted-foreground text-sm">
                        Pandangan menyeluruh lintas sekolah. Halaman ini hanya menampilkan — pengubahan data tetap lewat menu sekolah
                        masing-masing.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <TotalTile icon={Building2} label="Sekolah" value={totals.schools} />
                    <TotalTile icon={Users} label="Total Siswa" value={totals.students} />
                    <TotalTile icon={GraduationCap} label="Siswa Aktif" value={totals.active_students} />
                </div>

                <Tabs value={tab} onValueChange={(value) => go({ tab: value })}>
                    <TabsList>
                        {TABS.map((entry) => (
                            <TabsTrigger key={entry.value} value={entry.value}>
                                {entry.label}
                            </TabsTrigger>
                        ))}
                    </TabsList>
                </Tabs>

                {tab === 'ringkasan' && summary && (
                    <div className="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Sekolah</TableHead>
                                    <TableHead className="text-right">Siswa</TableHead>
                                    <TableHead className="text-right">Kelas</TableHead>
                                    <TableHead className="text-right">Hadir Hari Ini</TableHead>
                                    <TableHead className="text-right">Terlambat Hari Ini</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {summary.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="font-medium">{row.name}</TableCell>
                                        <TableCell className="text-right">{row.students_count}</TableCell>
                                        <TableCell className="text-right">{row.classrooms_count}</TableCell>
                                        <TableCell className="text-right">{row.hadir}</TableCell>
                                        <TableCell className="text-right">{row.terlambat}</TableCell>
                                        <TableCell>
                                            <Badge variant={row.is_active ? 'default' : 'secondary'}>
                                                {row.is_active ? 'Aktif' : 'Nonaktif'}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {tab === 'siswa' && students && (
                    <>
                        <div className="flex flex-col gap-3 sm:flex-row">
                            <div className="relative flex-1">
                                <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                <Input
                                    placeholder="Cari nama, NIS, atau NISN di semua sekolah..."
                                    value={search}
                                    onChange={(e) => {
                                        setSearch(e.target.value);
                                        go({ search: e.target.value });
                                    }}
                                    className="pl-9"
                                />
                            </div>
                            <SchoolFilter schools={schools} value={filters.school_id} onChange={(value) => go({ school_id: value })} />
                        </div>

                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Sekolah</TableHead>
                                        <TableHead>NIS</TableHead>
                                        <TableHead>NISN</TableHead>
                                        <TableHead>Nama</TableHead>
                                        <TableHead>Kelas</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Aksi</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {students.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={7} className="text-muted-foreground py-8 text-center">
                                                Tidak ada siswa yang cocok.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        students.data.map((student) => (
                                            <TableRow key={student.id}>
                                                <TableCell className="text-muted-foreground text-xs">{student.school ?? '-'}</TableCell>
                                                <TableCell className="font-medium">{student.nis ?? '-'}</TableCell>
                                                <TableCell>{student.nisn ?? '-'}</TableCell>
                                                <TableCell>{student.full_name}</TableCell>
                                                <TableCell>{student.classroom ?? '-'}</TableCell>
                                                <TableCell>
                                                    <Badge variant={student.is_active ? 'default' : 'secondary'}>
                                                        {student.is_active ? 'Aktif' : 'Nonaktif'}
                                                    </Badge>
                                                </TableCell>
                                                {/* POST, bukan <Link>: siswa di sini bisa milik sekolah mana pun,
                                                    sedangkan route siswa terkunci ke sekolah yang sedang aktif di
                                                    sesi. Tautan biasa akan 404. Route ini memindahkan konteks
                                                    sekolahnya lebih dulu, baru membuka halamannya. */}
                                                <TableCell className="text-right">
                                                    <div className="flex justify-end gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            title={`Buka detail di ${student.school ?? 'sekolahnya'}`}
                                                            onClick={() => bukaSiswa(student.id, 'show')}
                                                        >
                                                            <Eye className="size-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            title={`Ubah data di ${student.school ?? 'sekolahnya'}`}
                                                            onClick={() => bukaSiswa(student.id, 'edit')}
                                                        >
                                                            <Edit className="size-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        <Pagination page={students} />
                    </>
                )}

                {tab === 'absensi' && attendance && (
                    <>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div className="grid gap-1">
                                <Label htmlFor="start">Dari Tanggal</Label>
                                <Input id="start" type="date" value={start} onChange={(e) => setStart(e.target.value)} />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="end">Sampai Tanggal</Label>
                                <Input id="end" type="date" value={end} onChange={(e) => setEnd(e.target.value)} />
                            </div>
                            <Button onClick={() => go({ start, end })}>Terapkan</Button>
                        </div>

                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Sekolah</TableHead>
                                        <TableHead className="text-right">Hadir</TableHead>
                                        <TableHead className="text-right">Terlambat</TableHead>
                                        <TableHead className="text-right">Izin</TableHead>
                                        <TableHead className="text-right">Sakit</TableHead>
                                        <TableHead className="text-right">Alpa</TableHead>
                                        <TableHead className="text-right">Total</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {attendance.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell className="font-medium">{row.name}</TableCell>
                                            <TableCell className="text-right">{row.hadir}</TableCell>
                                            <TableCell className="text-right">{row.terlambat}</TableCell>
                                            <TableCell className="text-right">{row.izin}</TableCell>
                                            <TableCell className="text-right">{row.sakit}</TableCell>
                                            <TableCell className="text-right">{row.alpa}</TableCell>
                                            <TableCell className="text-right font-semibold">{row.total}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </>
                )}

                {tab === 'kartu' && cards && (
                    <>
                        <SchoolFilter schools={schools} value={filters.school_id} onChange={(value) => go({ school_id: value })} />

                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Sekolah</TableHead>
                                        <TableHead>Siswa</TableHead>
                                        <TableHead>Jenis</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Waktu</TableHead>
                                        <TableHead className="text-right">Berkas</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {cards.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={6} className="text-muted-foreground py-8 text-center">
                                                Belum ada riwayat.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        cards.data.map((row) => (
                                            <TableRow key={row.id}>
                                                <TableCell className="text-muted-foreground text-xs">{row.school ?? '-'}</TableCell>
                                                <TableCell>{row.student ?? '-'}</TableCell>
                                                <TableCell>{row.type}</TableCell>
                                                <TableCell>
                                                    <Badge variant={row.status === 'completed' ? 'default' : 'secondary'}>{row.status}</Badge>
                                                </TableCell>
                                                <TableCell className="text-muted-foreground text-xs">{row.created_at}</TableCell>
                                                <TableCell className="text-right">
                                                    {row.drive_url || row.file_url ? (
                                                        <Button variant="ghost" size="icon" asChild title="Buka berkas">
                                                            <a href={(row.drive_url ?? row.file_url) as string} target="_blank" rel="noreferrer">
                                                                <HardDrive className="size-4" />
                                                            </a>
                                                        </Button>
                                                    ) : (
                                                        <span className="text-muted-foreground text-xs">-</span>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        <Pagination page={cards} />
                    </>
                )}
            </div>
        </>
    );
}

function TotalTile({ icon: Icon, label, value }: { icon: typeof Users; label: string; value: number }) {
    return (
        <Card>
            <CardContent className="flex items-center gap-3 py-4">
                <div className="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-lg">
                    <Icon className="size-5" />
                </div>
                <div>
                    <p className="text-muted-foreground text-xs">{label}</p>
                    <p className="text-xl font-bold">{value}</p>
                </div>
            </CardContent>
        </Card>
    );
}

function SchoolFilter({ schools, value, onChange }: { schools: SchoolOption[]; value: string; onChange: (value: string) => void }) {
    return (
        <Select value={value || 'all'} onValueChange={(next) => onChange(next === 'all' ? '' : next)}>
            <SelectTrigger className="w-full sm:w-[240px]">
                <SelectValue placeholder="Semua Sekolah" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">Semua Sekolah</SelectItem>
                {schools.map((school) => (
                    <SelectItem key={school.id} value={school.id}>
                        {school.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function Pagination<T>({ page }: { page: Paginated<T> }) {
    if (page.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-between">
            <p className="text-muted-foreground text-sm">
                Menampilkan {page.from} - {page.to} dari {page.total}
            </p>
            <div className="flex gap-1">
                {page.links.map((link, index) => (
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
    );
}
