import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';

const roleLabels: Record<string, string> = {
    SUPER_ADMIN: 'Super Admin',
    ADMIN: 'Admin Sekolah',
    GURU: 'Guru',
    ORANG_TUA: 'Orang Tua',
};

type Role = { id: string; name: string };
type Module = { permission: string; label: string };
type ModuleGroup = { group: string; modules: Module[] };
type EditUser = {
    id: string;
    name: string;
    email: string;
    phone: string | null;
    is_active: boolean;
    role: string;
    extra_permissions: string[];
};

export default function UsersEdit({ editUser, roles, modules }: { editUser: EditUser; roles: Role[]; modules: ModuleGroup[] }) {
    const { data, setData, put, processing, errors } = useForm({
        name: editUser.name,
        email: editUser.email,
        phone: editUser.phone ?? '',
        role: editUser.role,
        is_active: editUser.is_active,
        extra_permissions: editUser.extra_permissions ?? [],
    });

    function toggleExtra(permission: string) {
        setData(
            'extra_permissions',
            data.extra_permissions.includes(permission)
                ? data.extra_permissions.filter((p) => p !== permission)
                : [...data.extra_permissions, permission],
        );
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/admin/users/${editUser.id}`);
    }

    return (
        <>
            <Head title="Edit Pengguna" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <Link href="/admin/users"><ArrowLeft className="size-4" /></Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Edit Pengguna</h1>
                        <p className="text-muted-foreground text-sm">Perbarui data {editUser.name}.</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="mx-auto w-full max-w-2xl space-y-6">
                    <Card>
                        <CardHeader><CardTitle>Data Pengguna</CardTitle></CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-2">
                                <Label>Nama Lengkap</Label>
                                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Email</Label>
                                <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                                <InputError message={errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Telepon</Label>
                                <Input value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                                <InputError message={errors.phone} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Role</Label>
                                <Select value={data.role} onValueChange={(v) => setData('role', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        {roles.map((role) => (
                                            <SelectItem key={role.id} value={role.name}>{roleLabels[role.name] ?? role.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.role} />
                            </div>
                            <div className="flex items-center gap-2.5">
                                <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(c) => setData('is_active', Boolean(c))} />
                                <Label htmlFor="is_active" className="font-normal">Akun Aktif</Label>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Akses Tambahan</CardTitle>
                            <p className="text-muted-foreground text-sm">
                                Modul di luar role. Kosongkan kalau cukup pakai akses bawaan role.
                            </p>
                        </CardHeader>
                        <CardContent className="grid gap-5">
                            {modules.map((group) => (
                                <div key={group.group} className="grid gap-2">
                                    <Label className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">{group.group}</Label>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {group.modules.map((module) => (
                                            <label key={module.permission} className="flex items-center gap-2 text-sm">
                                                <Checkbox
                                                    checked={data.extra_permissions.includes(module.permission)}
                                                    onCheckedChange={() => toggleExtra(module.permission)}
                                                />
                                                {module.label}
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <div className="flex gap-3">
                        <Button variant="outline" asChild className="flex-1"><Link href="/admin/users">Batal</Link></Button>
                        <Button type="submit" disabled={processing} className="flex-1">{processing && <Spinner />}Simpan Perubahan</Button>
                    </div>
                </form>
            </div>
        </>
    );
}

UsersEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pengguna', href: '/admin/users' },
        { title: 'Edit', href: '#' },
    ],
};
