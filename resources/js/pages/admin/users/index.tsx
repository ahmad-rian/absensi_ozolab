import { Head, Link, router } from '@inertiajs/react';
import { Edit, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
    AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { dashboard } from '@/routes';

type Role = { id: number; name: string };
type UserItem = { id: number; name: string; email: string; phone: string | null; is_active: boolean; roles: Role[] };
type PaginationLink = { url: string | null; label: string; active: boolean };
type Paginated = { data: UserItem[]; links: PaginationLink[]; from: number | null; to: number | null; total: number; last_page: number };

const roleLabels: Record<string, string> = {
    SUPER_ADMIN: 'Super Admin',
    ADMIN: 'Admin Sekolah',
    GURU: 'Guru',
    ORANG_TUA: 'Orang Tua',
};

const roleColors: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    SUPER_ADMIN: 'destructive',
    ADMIN: 'default',
    GURU: 'secondary',
    ORANG_TUA: 'outline',
};

export default function UsersIndex({ users, roles, filters }: {
    users: Paginated;
    roles: Role[];
    filters: { search: string; role: string };
    isSuperAdmin: boolean;
}) {
    const [search, setSearch] = useState(filters.search);

    function handleSearch(value: string) {
        setSearch(value);
        router.get('/admin/users', { search: value, role: filters.role || undefined }, { preserveState: true, replace: true });
    }

    function handleRoleFilter(value: string) {
        router.get('/admin/users', { search: filters.search || undefined, role: value === 'all' ? undefined : value }, { preserveState: true, replace: true });
    }

    function handleDelete(id: number) {
        router.delete(`/admin/users/${id}`, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Manajemen Pengguna" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Manajemen Pengguna</h1>
                        <p className="text-muted-foreground text-sm">Kelola pengguna dan akses per sekolah.</p>
                    </div>
                    <Button asChild>
                        <Link href="/admin/users/create">
                            <Plus className="mr-2 size-4" />
                            Tambah Pengguna
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-col gap-3 sm:flex-row">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input placeholder="Cari nama atau email..." value={search} onChange={(e) => handleSearch(e.target.value)} className="pl-9" />
                    </div>
                    <Select value={filters.role || 'all'} onValueChange={handleRoleFilter}>
                        <SelectTrigger className="w-full sm:w-[180px]">
                            <SelectValue placeholder="Semua Role" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua Role</SelectItem>
                            {roles.map((role) => (
                                <SelectItem key={role.id} value={role.name}>{roleLabels[role.name] ?? role.name}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nama</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Telepon</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground py-8 text-center">Tidak ada pengguna ditemukan.</TableCell>
                                </TableRow>
                            ) : users.data.map((user) => (
                                <TableRow key={user.id}>
                                    <TableCell className="font-medium">{user.name}</TableCell>
                                    <TableCell>{user.email}</TableCell>
                                    <TableCell>{user.phone ?? '-'}</TableCell>
                                    <TableCell>
                                        {user.roles.map((r) => (
                                            <Badge key={r.id} variant={roleColors[r.name] ?? 'secondary'} className="mr-1">
                                                {roleLabels[r.name] ?? r.name}
                                            </Badge>
                                        ))}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={user.is_active ? 'default' : 'secondary'}>{user.is_active ? 'Aktif' : 'Nonaktif'}</Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" size="icon" asChild>
                                                <Link href={`/admin/users/${user.id}/edit`}><Edit className="size-4" /></Link>
                                            </Button>
                                            <AlertDialog>
                                                <AlertDialogTrigger asChild>
                                                    <Button variant="ghost" size="icon"><Trash2 className="text-destructive size-4" /></Button>
                                                </AlertDialogTrigger>
                                                <AlertDialogContent>
                                                    <AlertDialogHeader>
                                                        <AlertDialogTitle>Hapus Pengguna</AlertDialogTitle>
                                                        <AlertDialogDescription>Yakin hapus {user.name}? Tindakan ini tidak bisa dibatalkan.</AlertDialogDescription>
                                                    </AlertDialogHeader>
                                                    <AlertDialogFooter>
                                                        <AlertDialogCancel>Batal</AlertDialogCancel>
                                                        <AlertDialogAction onClick={() => handleDelete(user.id)} className="bg-destructive text-white hover:bg-destructive/90">Hapus</AlertDialogAction>
                                                    </AlertDialogFooter>
                                                </AlertDialogContent>
                                            </AlertDialog>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                {users.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-muted-foreground text-sm">Menampilkan {users.from}-{users.to} dari {users.total}</p>
                        <div className="flex gap-1">
                            {users.links.map((link, i) => (
                                <Button key={i} variant={link.active ? 'default' : 'outline'} size="sm" disabled={!link.url}
                                    onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                    dangerouslySetInnerHTML={{ __html: link.label }} />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pengguna', href: '/admin/users' },
    ],
};
