import { Head, router, useForm } from '@inertiajs/react';
import { Edit, Lock, Plus, Shield, Trash2 } from 'lucide-react';
import { Fragment, type FormEvent, useState } from 'react';
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

type Module = { permission: string; label: string };
type ModuleGroup = { group: string; modules: Module[] };
type Role = {
    id: string;
    name: string;
    label: string;
    is_builtin: boolean;
    is_locked: boolean;
    permissions: string[];
    users_count: number;
};
type Props = { roles: Role[]; modules: ModuleGroup[] };

/** Bentuk minimal yang dipakai matrix, supaya form create & edit bisa berbagi helper. */
type PermissionForm = {
    data: { permissions: string[] };
    setData: (key: 'permissions', value: string[]) => void;
    processing: boolean;
};

export default function RolesIndex({ roles, modules }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [editingRole, setEditingRole] = useState<Role | null>(null);

    const allModules = modules.flatMap((g) => g.modules);
    const labelOf = (permission: string) =>
        allModules.find((m) => m.permission === permission)?.label ?? permission;

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

    function handleDelete(id: string) {
        router.delete(`/admin/roles/${id}`, { preserveScroll: true });
    }

    function togglePermission(form: PermissionForm, permission: string) {
        const current = form.data.permissions;
        form.setData('permissions', current.includes(permission) ? current.filter((p) => p !== permission) : [...current, permission]);
    }

    function toggleGroup(form: PermissionForm, group: ModuleGroup) {
        const perms = group.modules.map((m) => m.permission);
        const allChecked = perms.every((p) => form.data.permissions.includes(p));

        form.setData(
            'permissions',
            allChecked
                ? form.data.permissions.filter((p) => !perms.includes(p))
                : [...new Set([...form.data.permissions, ...perms])],
        );
    }

    function moduleMatrix(form: PermissionForm) {
        return (
            <div className="grid max-h-[55vh] gap-5 overflow-y-auto pr-1">
                {modules.map((group) => (
                    <div key={group.group} className="grid gap-2">
                        <div className="flex items-center justify-between">
                            <Label className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">{group.group}</Label>
                            <Button type="button" variant="ghost" size="sm" className="h-6 text-xs" onClick={() => toggleGroup(form, group)}>
                                Centang semua
                            </Button>
                        </div>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {group.modules.map((module) => (
                                <label key={module.permission} className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={form.data.permissions.includes(module.permission)}
                                        onCheckedChange={() => togglePermission(form, module.permission)}
                                    />
                                    {module.label}
                                </label>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        );
    }

    return (
        <>
            <Head title="Role & Hak Akses" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Role & Hak Akses</h1>
                        <p className="text-muted-foreground text-sm">
                            Hak akses diberikan per modul. Satu centang = akses penuh ke modul itu.
                        </p>
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
                                            <CardTitle className="flex items-center gap-2 text-base">
                                                {role.label}
                                                {role.is_builtin && <Badge variant="outline" className="text-[10px]">Bawaan</Badge>}
                                            </CardTitle>
                                            <CardDescription>{role.users_count} pengguna</CardDescription>
                                        </div>
                                    </div>
                                    <div className="flex gap-1">
                                        {role.is_locked ? (
                                            <span className="text-muted-foreground flex size-9 items-center justify-center" title="Akses penuh, tidak bisa diubah">
                                                <Lock className="size-4" />
                                            </span>
                                        ) : (
                                            <Button variant="ghost" size="icon" onClick={() => openEdit(role)}>
                                                <Edit className="size-4" />
                                            </Button>
                                        )}
                                        {!role.is_builtin && role.users_count === 0 && (
                                            <AlertDialog>
                                                <AlertDialogTrigger asChild>
                                                    <Button variant="ghost" size="icon"><Trash2 className="text-destructive size-4" /></Button>
                                                </AlertDialogTrigger>
                                                <AlertDialogContent>
                                                    <AlertDialogHeader>
                                                        <AlertDialogTitle>Hapus Role</AlertDialogTitle>
                                                        <AlertDialogDescription>Yakin hapus role "{role.label}"?</AlertDialogDescription>
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
                                    {role.is_locked && <Badge variant="secondary" className="text-xs">Akses penuh semua modul</Badge>}
                                    {!role.is_locked && role.permissions.map((permission) => (
                                        <Badge key={permission} variant="secondary" className="text-xs">{labelOf(permission)}</Badge>
                                    ))}
                                    {!role.is_locked && role.permissions.length === 0 && (
                                        <p className="text-muted-foreground text-xs">Tidak ada akses</p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Permission Matrix */}
                <Card>
                    <CardHeader>
                        <CardTitle>Matriks Akses Modul</CardTitle>
                        <CardDescription>Klik ikon edit pada kartu role di atas untuk mengubah akses.</CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-[250px]">Modul</TableHead>
                                    {roles.map((role) => (
                                        <TableHead key={role.id} className="text-center">{role.label}</TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {modules.map((group) => (
                                    <Fragment key={group.group}>
                                        <TableRow className="bg-muted/40">
                                            <TableCell colSpan={roles.length + 1} className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                                                {group.group}
                                            </TableCell>
                                        </TableRow>
                                        {group.modules.map((module) => (
                                            <TableRow key={module.permission}>
                                                <TableCell className="font-medium">{module.label}</TableCell>
                                                {roles.map((role) => (
                                                    <TableCell key={role.id} className="text-center">
                                                        {role.is_locked || role.permissions.includes(module.permission) ? (
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
                                    </Fragment>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            {/* Create Role Dialog */}
            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent className="sm:max-w-2xl">
                    <form onSubmit={handleCreate}>
                        <DialogHeader><DialogTitle>Tambah Role Baru</DialogTitle></DialogHeader>
                        <div className="grid gap-4 py-4">
                            <div className="grid gap-2">
                                <Label>Nama Role</Label>
                                <Input value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} placeholder="Nama role baru" />
                                {createForm.errors.name && <p className="text-destructive text-sm">{createForm.errors.name}</p>}
                            </div>
                            {moduleMatrix(createForm)}
                        </div>
                        <DialogFooter>
                            <Button type="submit" disabled={createForm.processing}>Simpan</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Permissions Dialog */}
            <Dialog open={!!editingRole} onOpenChange={(open) => { if (!open) setEditingRole(null); }}>
                <DialogContent className="sm:max-w-2xl">
                    <form onSubmit={handleEdit}>
                        <DialogHeader><DialogTitle>Akses Modul — {editingRole?.label}</DialogTitle></DialogHeader>
                        <div className="grid gap-2 py-4">{moduleMatrix(editForm)}</div>
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
        { title: 'Role & Hak Akses', href: '/admin/roles' },
    ],
};
