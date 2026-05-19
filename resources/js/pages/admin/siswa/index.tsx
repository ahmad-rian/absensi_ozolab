import { Head, Link, router } from '@inertiajs/react';
import { Edit, Eye, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { dashboard } from '@/routes';

type Classroom = {
    id: number;
    name: string;
};

type ParentUser = {
    id: number;
    name: string;
};

type ParentProfile = {
    id: number;
    user: ParentUser | null;
};

type Student = {
    id: number;
    nis: string | null;
    nisn: string | null;
    full_name: string;
    gender: string;
    is_active: boolean;
    classroom: Classroom | null;
    parent_profile: ParentProfile | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedStudents = {
    data: Student[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

type Filters = {
    search: string;
    classroom_id: string;
};

type PageProps = {
    students: PaginatedStudents;
    classrooms: Classroom[];
    filters: Filters;
};

export default function SiswaIndex({ students, classrooms, filters }: PageProps) {
    const [search, setSearch] = useState(filters.search);

    function handleSearch(value: string) {
        setSearch(value);
        router.get(
            '/admin/siswa',
            { search: value, classroom_id: filters.classroom_id || undefined },
            { preserveState: true, replace: true },
        );
    }

    function handleClassroomFilter(value: string) {
        router.get(
            '/admin/siswa',
            { search: filters.search || undefined, classroom_id: value === 'all' ? undefined : value },
            { preserveState: true, replace: true },
        );
    }

    function handleDelete(id: number) {
        router.delete(`/admin/siswa/${id}`, {
            preserveScroll: true,
        });
    }

    function genderLabel(gender: string): string {
        return gender === 'LAKI_LAKI' ? 'Laki-laki' : 'Perempuan';
    }

    return (
        <>
            <Head title="Data Siswa" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Data Siswa</h1>
                        <p className="text-muted-foreground text-sm">Kelola data siswa yang terdaftar.</p>
                    </div>
                    <Button asChild>
                        <Link href="/admin/siswa/create">
                            <Plus />
                            Tambah Siswa
                        </Link>
                    </Button>
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-3 sm:flex-row">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            placeholder="Cari nama atau NIS..."
                            value={search}
                            onChange={(e) => handleSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                    <Select value={filters.classroom_id || 'all'} onValueChange={handleClassroomFilter}>
                        <SelectTrigger className="w-full sm:w-[200px]">
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
                </div>

                {/* Table */}
                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>NIS</TableHead>
                                <TableHead>Nama</TableHead>
                                <TableHead>Kelas</TableHead>
                                <TableHead>Jenis Kelamin</TableHead>
                                <TableHead>Orang Tua</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {students.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={7} className="text-muted-foreground py-8 text-center">
                                        Tidak ada data siswa ditemukan.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                students.data.map((student) => (
                                    <TableRow key={student.id}>
                                        <TableCell className="font-medium">{student.nis ?? '-'}</TableCell>
                                        <TableCell>{student.full_name}</TableCell>
                                        <TableCell>{student.classroom?.name ?? '-'}</TableCell>
                                        <TableCell>{genderLabel(student.gender)}</TableCell>
                                        <TableCell>{student.parent_profile?.user?.name ?? '-'}</TableCell>
                                        <TableCell>
                                            <Badge variant={student.is_active ? 'default' : 'secondary'}>
                                                {student.is_active ? 'Aktif' : 'Nonaktif'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={`/admin/siswa/${student.id}`}>
                                                        <Eye className="size-4" />
                                                    </Link>
                                                </Button>
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={`/admin/siswa/${student.id}/edit`}>
                                                        <Edit className="size-4" />
                                                    </Link>
                                                </Button>
                                                <AlertDialog>
                                                    <AlertDialogTrigger asChild>
                                                        <Button variant="ghost" size="icon">
                                                            <Trash2 className="size-4 text-destructive" />
                                                        </Button>
                                                    </AlertDialogTrigger>
                                                    <AlertDialogContent>
                                                        <AlertDialogHeader>
                                                            <AlertDialogTitle>Hapus Siswa</AlertDialogTitle>
                                                            <AlertDialogDescription>
                                                                Apakah Anda yakin ingin menghapus data siswa{' '}
                                                                <strong>{student.full_name}</strong>? Tindakan ini tidak dapat
                                                                dibatalkan.
                                                            </AlertDialogDescription>
                                                        </AlertDialogHeader>
                                                        <AlertDialogFooter>
                                                            <AlertDialogCancel>Batal</AlertDialogCancel>
                                                            <AlertDialogAction
                                                                className="bg-destructive text-white hover:bg-destructive/90"
                                                                onClick={() => handleDelete(student.id)}
                                                            >
                                                                Hapus
                                                            </AlertDialogAction>
                                                        </AlertDialogFooter>
                                                    </AlertDialogContent>
                                                </AlertDialog>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* Pagination */}
                {students.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-muted-foreground text-sm">
                            Menampilkan {students.from} - {students.to} dari {students.total} siswa
                        </p>
                        <div className="flex gap-1">
                            {students.links.map((link, index) => (
                                <Button
                                    key={index}
                                    variant={link.active ? 'default' : 'outline'}
                                    size="sm"
                                    disabled={!link.url}
                                    onClick={() => {
                                        if (link.url) {
                                            router.get(link.url, {}, { preserveState: true });
                                        }
                                    }}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

SiswaIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Siswa', href: '/admin/siswa' },
    ],
};
