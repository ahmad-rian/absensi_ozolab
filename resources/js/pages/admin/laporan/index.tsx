import { Head, router } from '@inertiajs/react';
import { ArrowUpDown, BarChart3, Calendar, Clock, Download, FileText, Filter, UserX, Users } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { dashboard } from '@/routes';

type Classroom = { id: number; name: string };

type ReportRow = {
    student_id: number;
    nis: string;
    full_name: string;
    classroom_name: string;
    hadir: number;
    terlambat: number;
    izin: number;
    sakit: number;
    alpa: number;
    attendance_rate: number;
};

type Summary = {
    effective_days: number;
    total_hadir: number;
    total_terlambat: number;
    total_tidak_hadir: number;
};

type Filters = {
    start_date: string;
    end_date: string;
    classroom_id: string;
};

type Props = {
    reportData: ReportRow[];
    summary: Summary;
    classrooms: Classroom[];
    filters: Filters;
};

type SortKey = 'full_name' | 'attendance_rate';
type SortDir = 'asc' | 'desc';

export default function LaporanIndex({ reportData, summary, classrooms, filters }: Props) {
    const [startDate, setStartDate] = useState(filters.start_date);
    const [endDate, setEndDate] = useState(filters.end_date);
    const [classroomId, setClassroomId] = useState(filters.classroom_id);
    const [sortKey, setSortKey] = useState<SortKey>('full_name');
    const [sortDir, setSortDir] = useState<SortDir>('asc');

    function handleGenerate() {
        router.get(
            '/admin/laporan',
            {
                start_date: startDate,
                end_date: endDate,
                classroom_id: classroomId || undefined,
            },
            { preserveState: true },
        );
    }

    function toggleSort(key: SortKey) {
        if (sortKey === key) {
            setSortDir(sortDir === 'asc' ? 'desc' : 'asc');
        } else {
            setSortKey(key);
            setSortDir('asc');
        }
    }

    const sortedData = [...reportData].sort((a, b) => {
        const modifier = sortDir === 'asc' ? 1 : -1;
        if (sortKey === 'full_name') {
            return a.full_name.localeCompare(b.full_name) * modifier;
        }
        return (a.attendance_rate - b.attendance_rate) * modifier;
    });

    const summaryCards = [
        { label: 'Total Hari Efektif', value: summary.effective_days, icon: Calendar, color: 'text-blue-600' },
        { label: 'Total Hadir', value: summary.total_hadir, icon: Users, color: 'text-green-600' },
        { label: 'Total Terlambat', value: summary.total_terlambat, icon: Clock, color: 'text-yellow-600' },
        { label: 'Total Tidak Hadir', value: summary.total_tidak_hadir, icon: UserX, color: 'text-red-600' },
    ];

    return (
        <>
            <Head title="Laporan Kehadiran" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Page Header */}
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Laporan Kehadiran</h1>
                    <p className="text-muted-foreground text-sm">Rekap kehadiran siswa berdasarkan periode</p>
                </div>

                {/* Filter Bar */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex flex-wrap items-end gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="start_date" className="text-sm font-medium">Tanggal Mulai</Label>
                                <Input
                                    id="start_date"
                                    type="date"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
                                    className="w-44"
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="end_date" className="text-sm font-medium">Tanggal Akhir</Label>
                                <Input
                                    id="end_date"
                                    type="date"
                                    value={endDate}
                                    onChange={(e) => setEndDate(e.target.value)}
                                    className="w-44"
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label className="text-sm font-medium">Kelas</Label>
                                <Select value={classroomId} onValueChange={setClassroomId}>
                                    <SelectTrigger className="w-48">
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
                            <Button onClick={handleGenerate} className="gap-2">
                                <Filter className="size-4" />
                                Generate
                            </Button>
                            <Button variant="outline" className="gap-2" asChild>
                                <a
                                    href={`/admin/laporan/export?start_date=${startDate}&end_date=${endDate}${classroomId && classroomId !== 'all' ? `&classroom_id=${classroomId}` : ''}`}
                                >
                                    <Download className="size-4" />
                                    Export CSV
                                </a>
                            </Button>
                            <Button variant="outline" className="gap-2" asChild>
                                <a
                                    href={`/admin/laporan/export-pdf?start_date=${startDate}&end_date=${endDate}${classroomId && classroomId !== 'all' ? `&classroom_id=${classroomId}` : ''}`}
                                >
                                    <FileText className="size-4" />
                                    Export PDF
                                </a>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Summary Cards */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    {summaryCards.map((card) => (
                        <Card key={card.label}>
                            <CardContent className="flex items-center gap-4 pt-6">
                                <div className={`flex size-12 shrink-0 items-center justify-center rounded-xl bg-muted ${card.color}`}>
                                    <card.icon className="size-6" />
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-xs font-medium">{card.label}</p>
                                    <p className="text-2xl font-bold">{card.value}</p>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Data Table */}
                <Card>
                    <CardContent className="pt-6">
                        {sortedData.length === 0 ? (
                            <div className="text-muted-foreground flex flex-col items-center justify-center py-12 text-center">
                                <BarChart3 className="mb-3 size-12 opacity-30" />
                                <p className="text-lg font-medium">Belum ada data</p>
                                <p className="text-sm">Pilih periode dan klik Generate untuk menampilkan laporan.</p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12">#</TableHead>
                                        <TableHead>NIS</TableHead>
                                        <TableHead>
                                            <button
                                                type="button"
                                                className="flex items-center gap-1 font-medium"
                                                onClick={() => toggleSort('full_name')}
                                            >
                                                Nama Siswa
                                                <ArrowUpDown className="size-3.5" />
                                            </button>
                                        </TableHead>
                                        <TableHead>Kelas</TableHead>
                                        <TableHead className="text-center">Hadir</TableHead>
                                        <TableHead className="text-center">Terlambat</TableHead>
                                        <TableHead className="text-center">Izin</TableHead>
                                        <TableHead className="text-center">Sakit</TableHead>
                                        <TableHead className="text-center">Alpa</TableHead>
                                        <TableHead>
                                            <button
                                                type="button"
                                                className="flex items-center gap-1 font-medium"
                                                onClick={() => toggleSort('attendance_rate')}
                                            >
                                                % Kehadiran
                                                <ArrowUpDown className="size-3.5" />
                                            </button>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {sortedData.map((row, index) => (
                                        <TableRow key={row.student_id}>
                                            <TableCell className="text-muted-foreground">{index + 1}</TableCell>
                                            <TableCell className="font-mono text-xs">{row.nis}</TableCell>
                                            <TableCell className="font-medium">{row.full_name}</TableCell>
                                            <TableCell>{row.classroom_name}</TableCell>
                                            <TableCell className="text-center">
                                                <span className="inline-flex min-w-7 justify-center rounded-md bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                                    {row.hadir}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <span className="inline-flex min-w-7 justify-center rounded-md bg-yellow-100 px-2 py-0.5 text-xs font-semibold text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                                                    {row.terlambat}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <span className="inline-flex min-w-7 justify-center rounded-md bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                                    {row.izin}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <span className="inline-flex min-w-7 justify-center rounded-md bg-purple-100 px-2 py-0.5 text-xs font-semibold text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                                                    {row.sakit}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <span className="inline-flex min-w-7 justify-center rounded-md bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                                    {row.alpa}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <div className="bg-muted h-2 w-16 overflow-hidden rounded-full">
                                                        <div
                                                            className={`h-full rounded-full ${
                                                                row.attendance_rate >= 80
                                                                    ? 'bg-green-500'
                                                                    : row.attendance_rate >= 60
                                                                      ? 'bg-yellow-500'
                                                                      : 'bg-red-500'
                                                            }`}
                                                            style={{ width: `${row.attendance_rate}%` }}
                                                        />
                                                    </div>
                                                    <span className="text-sm font-semibold">{row.attendance_rate}%</span>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

LaporanIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Laporan', href: '/admin/laporan' },
    ],
};
