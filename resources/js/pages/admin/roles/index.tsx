import { Head, router, useForm } from '@inertiajs/react';
import { Edit, Plus, Shield, Trash2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
    AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { dashboard } from '@/routes';

type Role = { id: number; name: string; permissions: string[]; users_count: number };
type Props = { roles: Role[]; permissions: string[] };

const roleLabels: Record<string, string> = {
    SUPER_ADMIN: 'Super Admin', ADMIN: 'Admin Sekolah', GURU: 'Guru / Operator', ORANG_TUA: 'Orang Tua / Wali',
};

const permissionLabels: Record<string, string> = {
    'dashboard.view': 'Lihat Dashboard', 'student.view': 'Lihat Siswa', 'student.create': 'Tambah Siswa',
    'student.update': 'Edit Siswa', 'student.delete': 'Hapus Siswa', 'attendance.view': 'Lihat Absensi',
    'attendance.create': 'Catat Absensi', 'attendance.export': 'Ekspor Absensi', 'classroom.view': 'Lihat Kelas',
    'classroom.manage': 'Kelola Kelas', 'report.view': 'Lihat Laporan', 'setting.manage': 'Kelola Pengaturan',
    'user.view': 'Lihat Pengguna', 'user.create': 'Tambah Pengguna', 'user.update': 'Edit Pengguna',
    'user.delete': 'Hapus Pengguna', 'school.manage': 'Kelola Sekolah',
};

export default function RolesIndex({ roles, permissions }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [editingRole, setEditingRole] = useState<Role | null>(null);

    const createForm = useForm({ name: '', permissions: [] as string[] });
    const editForm = useForm({ permissions: [] as string[] });

    function handleCreate(e: FormEvent) {
        e.preventDefault();
        createForm.post('/admin/roles', { preserveScroll: true, onSuccess: () => { setCreateOpen(false); createForm.reset(); } });
    }

    function openEdit(role: Role) {
        editForm.setData('permissions', [...role.permissions]);
        setEditingRole(role);
    }

    function handleEdit(e: FormEvent) {
        e.preventDefault();
        if (!editingRole) return;
        editForm.put(`/admin/roles/${editingRole.id}`, { preserveScroll: true, onSuccess: () => setEditingRole(null) });
    }

    function handleDelete(id: number) {
        router.delete(`/admin/roles/${id}`, { preserveScroll: true });
    }

    function togglePermission(form: typeof createForm | typeof editForm, perm: string) {
        const current = form.data.permissions;
        form.setData('permissions', current.includes(perm) ? current.filter((p) => p !== perm) : [...current, perm]);
    }

    return (
        <>
            <Head title="Role & Izin" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Role & Izin Akses</h1>
                        <p className="text-muted-foreground text-sm">Kelola role pengguna dan izin akses sistem.</p>
                    </div>
                    <Button onClick={() => setCreateOpen(true)}>
                        <Plus className="mr-2 size-4" />
                        Tambah Role
                    </Button>
                </div>

                {/* Role Cards */}
                <div className="grid gap-4 md:grid-cols-3">
                    {roles.map((role) => (
                        <Card key={role.id}>
                            <CardHeader className="pb-3">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        <div className="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-xl">
                                            <Shield className="size-5" />
                                        </div>
                                        <div>
                                            <CardTitle className="text-base">{roleLabels[role.name] ?? role.name}</CardTitle>
                                            <CardDescription>{role.users_count} pengguna</CardDescription>
                                        </div>
                                    </div>
                                    <div className="flex gap-1">
                                        <Button variant="ghost" size="icon" onClick={() => openEdit(role)}>
                                            <Edit className="size-4" />
                                        </Button>
                                        {role.users_count === 0 && (
                                            <AlertDialog>
                                                <AlertDialogTrigger asChild>
                                                    <Button variant="ghost" size="icon"><Trash2 className="text-destructive size-4" /></Button>
                                                </AlertDialogTrigger>
                                                <AlertDialogContent>
                                                    <AlertDialogHeader>
                                                        <AlertDialogTitle>Hapus Role</AlertDialogTitle>
                                                        <AlertDialogDescription>Yakin hapus role "{roleLabels[role.name] ?? role.name}"?</AlertDialogDescription>
                                                    </AlertDialogHeader>
                                                    <AlertDialogFooter>
                                                        <AlertDialogCancel>Batal</AlertDialogCancel>
                                                        <AlertDialogAction onClick={() => handleDelete(role.id)} className="bg-destructive text-white hover:bg-destructive/90">Hapus</AlertDialogAction>
                                                    </AlertDialogFooter>
                                                </AlertDialogContent>
                                            </AlertDialog>
                                        )}
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-wrap gap-1.5">
                                    {role.permissions.map((perm) => (
                                        <Badge key={perm} variant="secondary" className="text-xs">{permissionLabels[perm] ?? perm}</Badge>
                                    ))}
                                    {role.permissions.length === 0 && <p className="text-muted-foreground text-xs">Tidak ada izin</p>}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Permission Matrix */}
                <Card>
                    <CardHeader>
                        <CardTitle>Matriks Izin Akses</CardTitle>
                        <CardDescription>Klik ikon edit pada role card di atas untuk mengubah izin.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-[250px]">Izin</TableHead>
                                    {roles.map((role) => (
                                        <TableHead key={role.id} className="text-center">{roleLabels[role.name] ?? role.name}</TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {permissions.map((perm) => (
                                    <TableRow key={perm}>
                                        <TableCell className="font-medium">{permissionLabels[perm] ?? perm}</TableCell>
                                        {roles.map((role) => (
                                            <TableCell key={role.id} className="text-center">
                                                {role.permissions.includes(perm) ? (
                                                    <span className="inline-flex size-5 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300">
                                                        <svg className="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}><path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" /></svg>
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex size-5 items-center justify-center rounded-full bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-600">
                                                        <svg className="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                                    </span>
                                                )}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            {/* Create Role Dialog */}
            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent>
                    <form onSubmit={handleCreate}>
                        <DialogHeader><DialogTitle>Tambah Role Baru</DialogTitle></DialogHeader>
                        <div className="grid gap-4 py-4">
                            <div className="grid gap-2">
                                <Label>Nama Role</Label>
                                <Input value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} placeholder="Nama role baru" />
                                {createForm.errors.name && <p className="text-destructive text-sm">{createForm.errors.name}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label>Izin Akses</Label>
                                <div className="grid grid-cols-2 gap-2">
                                    {permissions.map((perm) => (
                                        <label key={perm} className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={createForm.data.permissions.includes(perm)}
                                                onCheckedChange={() => togglePermission(createForm, perm)}
                                            />
                                            {permissionLabels[perm] ?? perm}
                                        </label>
                                    ))}
                                </div>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="submit" disabled={createForm.processing}>Simpan</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Permissions Dialog */}
            <Dialog open={!!editingRole} onOpenChange={(open) => { if (!open) setEditingRole(null); }}>
                <DialogContent>
                    <form onSubmit={handleEdit}>
                        <DialogHeader><DialogTitle>Edit Izin — {roleLabels[editingRole?.name ?? ''] ?? editingRole?.name}</DialogTitle></DialogHeader>
                        <div className="grid gap-2 py-4">
                            <Label>Izin Akses</Label>
                            <div className="grid grid-cols-2 gap-2">
                                {permissions.map((perm) => (
                                    <label key={perm} className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            checked={editForm.data.permissions.includes(perm)}
                                            onCheckedChange={() => togglePermission(editForm, perm)}
                                        />
                                        {permissionLabels[perm] ?? perm}
                                    </label>
                                ))}
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="submit" disabled={editForm.processing}>Simpan Perubahan</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Role & Izin', href: '/admin/roles' },
    ],
};
