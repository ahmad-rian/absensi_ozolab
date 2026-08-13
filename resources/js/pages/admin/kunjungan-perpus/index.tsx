import { Head, Link, router } from '@inertiajs/react';
import { BookOpen, DoorOpen, Search, Users } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

type Classroom = { id: string; name: string };

type VisitRow = {
    id: string;
    student: string | null;
    nis: string | null;
    classroom: string | null;
    date: string;
    entered_at: string;
    exited_at: string | null;
    duration_minutes: number | null;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

type PageProps = {
    visits: {
        data: VisitRow[];
        links: PaginationLink[];
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
    classrooms: Classroom[];
    filters: { search: string; classroom_id: string; start: string; end: string };
    summary: { visits: number; students: number; inside: number };
};

function durationLabel(minutes: number | null): string {
    if (minutes === null) {
        return 'Belum keluar';
    }

    if (minutes < 60) {
        return `${minutes} menit`;
    }

    const jam = Math.floor(minutes / 60);
    const sisa = minutes % 60;

    return sisa === 0 ? `${jam} jam` : `${jam} jam ${sisa} menit`;
}

export default function KunjunganPerpusIndex({ visits, classrooms, filters, summary }: PageProps) {
    const [search, setSearch] = useState(filters.search);
    const [start, setStart] = useState(filters.start);
    const [end, setEnd] = useState(filters.end);

    function go(next: Partial<PageProps['filters']>) {
        const merged = { ...filters, ...next };

        router.get(
            '/admin/kunjungan-perpus',
            {
                search: merged.search || undefined,
                classroom_id: merged.classroom_id || undefined,
                start: merged.start || undefined,
                end: merged.end || undefined,
            },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Kunjungan Perpus" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Kunjungan Perpustakaan</h1>
                    <p className="text-muted-foreground text-sm">
                        Catatan masuk dan keluar dari halaman scan perpustakaan.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Tile icon={BookOpen} label="Kunjungan" value={summary.visits} />
                    <Tile icon={Users} label="Siswa Berkunjung" value={summary.students} />
                    <Tile icon={DoorOpen} label="Sedang di Dalam" value={summary.inside} />
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            placeholder="Cari nama, NIS, atau NISN..."
                            value={search}
                            onChange={(e) => {
                                setSearch(e.target.value);
                                go({ search: e.target.value });
                            }}
                            className="pl-9"
                        />
                    </div>
                    <Select
                        value={filters.classroom_id || 'all'}
                        onValueChange={(value) => go({ classroom_id: value === 'all' ? '' : value })}
                    >
                        <SelectTrigger className="w-full sm:w-[180px]">
                            <SelectValue placeholder="Semua Kelas" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua Kelas</SelectItem>
                            {classrooms.map((classroom) => (
                                <SelectItem key={classroom.id} value={String(classroom.id)}>
                                    {classroom.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <div className="grid gap-1">
                        <Label htmlFor="start">Dari</Label>
                        <Input id="start" type="date" value={start} onChange={(e) => setStart(e.target.value)} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="end">Sampai</Label>
                        <Input id="end" type="date" value={end} onChange={(e) => setEnd(e.target.value)} />
                    </div>
                    <Button onClick={() => go({ start, end })}>Terapkan</Button>
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Tanggal</TableHead>
                                <TableHead>NIS</TableHead>
                                <TableHead>Nama</TableHead>
                                <TableHead>Kelas</TableHead>
                                <TableHead>Masuk</TableHead>
                                <TableHead>Keluar</TableHead>
                                <TableHead>Lama</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {visits.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={7} className="text-muted-foreground py-8 text-center">
                                        Belum ada kunjungan pada rentang ini.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                visits.data.map((visit) => (
                                    <TableRow key={visit.id}>
                                        <TableCell className="text-muted-foreground text-xs">{visit.date}</TableCell>
                                        <TableCell className="font-medium">{visit.nis ?? '-'}</TableCell>
                                        <TableCell>{visit.student ?? '-'}</TableCell>
                                        <TableCell>{visit.classroom ?? '-'}</TableCell>
                                        <TableCell>{visit.entered_at}</TableCell>
                                        <TableCell>{visit.exited_at ?? '-'}</TableCell>
                                        <TableCell
                                            className={visit.duration_minutes === null ? 'text-muted-foreground text-xs' : ''}
                                        >
                                            {durationLabel(visit.duration_minutes)}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                {visits.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-muted-foreground text-sm">
                            Menampilkan {visits.from} - {visits.to} dari {visits.total} kunjungan
                        </p>
                        <div className="flex gap-1">
                            {visits.links.map((link, index) => (
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
                )}
            </div>
        </>
    );
}

function Tile({ icon: Icon, label, value }: { icon: typeof Users; label: string; value: number }) {
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
