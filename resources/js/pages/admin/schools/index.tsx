import { Head, Link, router } from '@inertiajs/react';
import { Building2, Edit, Globe, Mail, MapPin, Phone, Plus, Search, Trash2, Users } from 'lucide-react';
import { useState } from 'react';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
    AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';

type School = {
    id: number;
    name: string;
    slug: string;
    address: string | null;
    city: string | null;
    phone: string | null;
    email: string | null;
    website: string | null;
    is_active: boolean;
    users_count: number;
    students_count: number;
    classrooms_count: number;
};

type PaginationLink = { url: string | null; label: string; active: boolean };
type Paginated = { data: School[]; links: PaginationLink[]; from: number | null; to: number | null; total: number; last_page: number };

export default function SchoolsIndex({ schools, filters }: { schools: Paginated; filters: { search: string } }) {
    const [search, setSearch] = useState(filters.search);

    function handleSearch(value: string) {
        setSearch(value);
        router.get('/admin/schools', { search: value || undefined }, { preserveState: true, replace: true });
    }

    return (
        <>
            <Head title="Manajemen Sekolah" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Manajemen Sekolah</h1>
                        <p className="text-muted-foreground text-sm">Kelola sekolah yang terdaftar di platform.</p>
                    </div>
                    <Button asChild>
                        <Link href="/admin/schools/create">
                            <Plus className="mr-2 size-4" />
                            Tambah Sekolah
                        </Link>
                    </Button>
                </div>

                <div className="relative max-w-sm">
                    <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                    <Input placeholder="Cari sekolah..." value={search} onChange={(e) => handleSearch(e.target.value)} className="pl-9" />
                </div>

                {schools.data.length === 0 ? (
                    <div className="flex flex-1 flex-col items-center justify-center gap-2 py-12">
                        <Building2 className="text-muted-foreground size-12" />
                        <p className="text-muted-foreground text-sm">Belum ada sekolah terdaftar.</p>
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {schools.data.map((school) => (
                            <Card key={school.id} className="relative">
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between">
                                        <div className="flex items-center gap-3">
                                            <div className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl">
                                                <Building2 className="size-5" />
                                            </div>
                                            <div>
                                                <CardTitle className="text-base">{school.name}</CardTitle>
                                                <p className="text-muted-foreground text-xs">{school.slug}</p>
                                            </div>
                                        </div>
                                        <Badge variant={school.is_active ? 'default' : 'secondary'}>
                                            {school.is_active ? 'Aktif' : 'Nonaktif'}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-2 pb-3">
                                    <div className="grid grid-cols-3 gap-2 text-center">
                                        <div className="rounded-lg bg-muted/50 px-2 py-1.5">
                                            <p className="text-lg font-bold">{school.students_count}</p>
                                            <p className="text-muted-foreground text-[10px]">Siswa</p>
                                        </div>
                                        <div className="rounded-lg bg-muted/50 px-2 py-1.5">
                                            <p className="text-lg font-bold">{school.classrooms_count}</p>
                                            <p className="text-muted-foreground text-[10px]">Kelas</p>
                                        </div>
                                        <div className="rounded-lg bg-muted/50 px-2 py-1.5">
                                            <p className="text-lg font-bold">{school.users_count}</p>
                                            <p className="text-muted-foreground text-[10px]">Pengguna</p>
                                        </div>
                                    </div>
                                    {(school.city || school.phone || school.email) && (
                                        <div className="text-muted-foreground space-y-1 pt-1 text-xs">
                                            {school.city && <p className="flex items-center gap-1.5"><MapPin className="size-3" />{school.city}</p>}
                                            {school.phone && <p className="flex items-center gap-1.5"><Phone className="size-3" />{school.phone}</p>}
                                            {school.email && <p className="flex items-center gap-1.5"><Mail className="size-3" />{school.email}</p>}
                                        </div>
                                    )}
                                </CardContent>
                                <CardFooter className="gap-2 pt-0">
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={`/admin/schools/${school.id}/edit`}><Edit className="mr-1 size-3.5" />Edit</Link>
                                    </Button>
                                    <AlertDialog>
                                        <AlertDialogTrigger asChild>
                                            <Button variant="outline" size="sm"><Trash2 className="text-destructive mr-1 size-3.5" />Hapus</Button>
                                        </AlertDialogTrigger>
                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle>Hapus Sekolah</AlertDialogTitle>
                                                <AlertDialogDescription>Yakin hapus {school.name}? Sekolah dengan siswa terdaftar tidak bisa dihapus.</AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                                <AlertDialogAction onClick={() => router.delete(`/admin/schools/${school.id}`, { preserveScroll: true })} className="bg-destructive text-white hover:bg-destructive/90">Hapus</AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                )}

                {schools.last_page > 1 && (
                    <div className="flex justify-center gap-1">
                        {schools.links.map((link, i) => (
                            <Button key={i} variant={link.active ? 'default' : 'outline'} size="sm" disabled={!link.url}
                                onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                dangerouslySetInnerHTML={{ __html: link.label }} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

SchoolsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Sekolah', href: '/admin/schools' },
    ],
};
