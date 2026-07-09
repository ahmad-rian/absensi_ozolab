import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';

type Classroom = {
    id: string;
    name: string;
};

type Student = {
    id: string;
    nis: string;
    full_name: string;
    classroom_id: string | null;
};

type Attendance = {
    id: string;
    attendance_date: string;
    recorded_at: string;
    type: string;
    status: string;
    notes: string | null;
    student: {
        id: string;
        full_name: string;
        classroom: {
            id: string;
            name: string;
        } | null;
    };
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
};

type Filters = {
    date: string;
    classroom_id: string;
    status: string;
    type: string;
};

type PageProps = {
    attendances: Paginated<Attendance>;
    classrooms: Classroom[];
    students: Student[];
    filters: Filters;
    flash?: {
        success?: string;
        error?: string;
    };
};

const statusConfig: Record<string, { label: string; className: string }> = {
    HADIR: { label: 'Hadir', className: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' },
    TERLAMBAT: { label: 'Terlambat', className: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300' },
    ALPA: { label: 'Alpa', className: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300' },
    IZIN: { label: 'Izin', className: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300' },
    SAKIT: { label: 'Sakit', className: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300' },
};

const typeConfig: Record<string, { label: string; className: string }> = {
    CHECK_IN: { label: 'Check In', className: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-300' },
    CHECK_OUT: { label: 'Check Out', className: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300' },
};

// recorded_at/attendance_date disimpan sebagai WIB wall-clock (bukan UTC murni),
// jadi render dengan timeZone 'UTC' agar menampilkan digit tersimpan apa adanya
// tanpa konversi ulang ke timezone browser (yang bikin +7 jam).
function formatDate(dateStr: string): string {
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'UTC' }).format(new Date(dateStr));
}

function formatTime(dateStr: string): string {
    return new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', timeZone: 'UTC' }).format(new Date(dateStr));
}

// Datetime-local (YYYY-MM-DDTHH:mm) untuk waktu sekarang dalam WIB.
function nowWibLocal(): string {
    return new Date().toLocaleString('sv-SE', { timeZone: 'Asia/Jakarta' }).slice(0, 16).replace(' ', 'T');
}

export default function AbsensiIndex() {
    const { attendances, classrooms, students, filters, flash } = usePage<PageProps>().props;
    const [dialogOpen, setDialogOpen] = useState(false);
    const [studentSearch, setStudentSearch] = useState('');

    const form = useForm({
        student_id: '',
        type: 'CHECK_IN',
        status: 'HADIR',
        notes: '',
        recorded_at: nowWibLocal(),
    });

    function applyFilter(key: string, value: string) {
        router.get(
            '/admin/absensi',
            { ...filters, [key]: value, page: undefined },
            { preserveState: true, preserveScroll: true },
        );
    }

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        form.post('/admin/absensi', {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                form.reset();
            },
        });
    }

    const filteredStudents = students.filter(
        (s) =>
            s.full_name.toLowerCase().includes(studentSearch.toLowerCase()) ||
            s.nis.includes(studentSearch),
    );

    return (
        <>
            <Head title="Rekap Absensi" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                {/* Page Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Rekap Absensi</h1>
                        <p className="text-muted-foreground text-sm">Lihat rekap absensi harian dan kelola status kehadiran.</p>
                    </div>
                    <Button onClick={() => setDialogOpen(true)}>
                        <Plus className="mr-2 size-4" />
                        Input Manual
                    </Button>
                </div>

                {/* Flash Messages */}
                {flash?.success && (
                    <div className="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-300">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-300">
                        {flash.error}
                    </div>
                )}

                {/* Filter Bar */}
                <Card>
                    <CardContent className="pt-0">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="space-y-1.5">
                                <Label>Tanggal</Label>
                                <Input
                                    type="date"
                                    value={filters.date}
                                    onChange={(e) => applyFilter('date', e.target.value)}
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label>Kelas</Label>
                                <Select
                                    value={filters.classroom_id}
                                    onValueChange={(val) => applyFilter('classroom_id', val === 'all' ? '' : val)}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Semua Kelas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Semua Kelas</SelectItem>
                                        {classrooms.map((c) => (
                                            <SelectItem key={c.id} value={String(c.id)}>
                                                {c.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-1.5">
                                <Label>Status</Label>
                                <Select
                                    value={filters.status}
                                    onValueChange={(val) => applyFilter('status', val === 'all' ? '' : val)}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Semua Status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Semua Status</SelectItem>
                                        <SelectItem value="HADIR">Hadir</SelectItem>
                                        <SelectItem value="TERLAMBAT">Terlambat</SelectItem>
                                        <SelectItem value="IZIN">Izin</SelectItem>
                                        <SelectItem value="SAKIT">Sakit</SelectItem>
                                        <SelectItem value="ALPA">Alpa</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-1.5">
                                <Label>Tipe</Label>
                                <Select
                                    value={filters.type}
                                    onValueChange={(val) => applyFilter('type', val === 'all' ? '' : val)}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Semua Tipe" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Semua Tipe</SelectItem>
                                        <SelectItem value="CHECK_IN">Check In</SelectItem>
                                        <SelectItem value="CHECK_OUT">Check Out</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Data Table */}
                <Card>
                    <CardContent className="pt-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Tanggal</TableHead>
                                    <TableHead>Waktu</TableHead>
                                    <TableHead>Nama Siswa</TableHead>
                                    <TableHead>Kelas</TableHead>
                                    <TableHead>Tipe</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Catatan</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {attendances.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={7} className="text-muted-foreground py-8 text-center">
                                            Tidak ada data absensi.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    attendances.data.map((a) => (
                                        <TableRow key={a.id}>
                                            <TableCell>{formatDate(a.attendance_date)}</TableCell>
                                            <TableCell>{formatTime(a.recorded_at)}</TableCell>
                                            <TableCell className="font-medium">{a.student.full_name}</TableCell>
                                            <TableCell>{a.student.classroom?.name ?? '-'}</TableCell>
                                            <TableCell>
                                                <Badge variant="outline" className={typeConfig[a.type]?.className}>
                                                    {typeConfig[a.type]?.label ?? a.type}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline" className={statusConfig[a.status]?.className}>
                                                    {statusConfig[a.status]?.label ?? a.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground max-w-[200px] truncate">
                                                {a.notes ?? '-'}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>

                        {/* Pagination */}
                        {attendances.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-center gap-1">
                                {attendances.links.map((link, i) => (
                                    <Button
                                        key={i}
                                        variant={link.active ? 'default' : 'outline'}
                                        size="sm"
                                        disabled={!link.url}
                                        onClick={() => {
                                            if (link.url) {
                                                router.get(link.url, {}, { preserveState: true, preserveScroll: true });
                                            }
                                        }}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Input Manual Dialog */}
                <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Input Absensi Manual</DialogTitle>
                        </DialogHeader>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="space-y-1.5">
                                <Label>Cari Siswa</Label>
                                <Input
                                    type="text"
                                    placeholder="Ketik nama atau NIS siswa..."
                                    value={studentSearch}
                                    onChange={(e) => setStudentSearch(e.target.value)}
                                />
                                {studentSearch && (
                                    <div className="max-h-40 overflow-y-auto rounded-md border">
                                        {filteredStudents.slice(0, 10).map((s) => (
                                            <button
                                                key={s.id}
                                                type="button"
                                                className="hover:bg-accent w-full px-3 py-2 text-left text-sm"
                                                onClick={() => {
                                                    form.setData('student_id', String(s.id));
                                                    setStudentSearch(s.full_name + ' (' + s.nis + ')');
                                                }}
                                            >
                                                {s.full_name} <span className="text-muted-foreground">({s.nis})</span>
                                            </button>
                                        ))}
                                        {filteredStudents.length === 0 && (
                                            <p className="text-muted-foreground px-3 py-2 text-sm">Siswa tidak ditemukan.</p>
                                        )}
                                    </div>
                                )}
                                {form.errors.student_id && <p className="text-sm text-red-500">{form.errors.student_id}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-1.5">
                                    <Label>Tipe</Label>
                                    <Select value={form.data.type} onValueChange={(val) => form.setData('type', val)}>
                                        <SelectTrigger className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="CHECK_IN">Check In</SelectItem>
                                            <SelectItem value="CHECK_OUT">Check Out</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {form.errors.type && <p className="text-sm text-red-500">{form.errors.type}</p>}
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Status</Label>
                                    <Select value={form.data.status} onValueChange={(val) => form.setData('status', val)}>
                                        <SelectTrigger className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="HADIR">Hadir</SelectItem>
                                            <SelectItem value="TERLAMBAT">Terlambat</SelectItem>
                                            <SelectItem value="IZIN">Izin</SelectItem>
                                            <SelectItem value="SAKIT">Sakit</SelectItem>
                                            <SelectItem value="ALPA">Alpa</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {form.errors.status && <p className="text-sm text-red-500">{form.errors.status}</p>}
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <Label>Waktu</Label>
                                <Input
                                    type="datetime-local"
                                    value={form.data.recorded_at}
                                    onChange={(e) => form.setData('recorded_at', e.target.value)}
                                />
                                {form.errors.recorded_at && <p className="text-sm text-red-500">{form.errors.recorded_at}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <Label>Catatan</Label>
                                <Textarea
                                    placeholder="Catatan opsional..."
                                    value={form.data.notes}
                                    onChange={(e) => form.setData('notes', e.target.value)}
                                />
                                {form.errors.notes && <p className="text-sm text-red-500">{form.errors.notes}</p>}
                            </div>

                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                                    Batal
                                </Button>
                                <Button type="submit" disabled={form.processing}>
                                    {form.processing ? 'Menyimpan...' : 'Simpan'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

AbsensiIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Absensi', href: '/admin/absensi' },
    ],
};
