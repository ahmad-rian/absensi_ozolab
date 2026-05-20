import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';

const roleLabels: Record<string, string> = { ADMIN: 'Admin Sekolah', GURU: 'Guru' };

type Role = { id: number; name: string };

export default function UsersCreate({ roles }: { roles: Role[] }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
        role: '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post('/admin/users');
    }

    return (
        <>
            <Head title="Tambah Pengguna" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <Link href="/admin/users"><ArrowLeft className="size-4" /></Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Tambah Pengguna</h1>
                        <p className="text-muted-foreground text-sm">Buat akun operator/admin untuk sekolah ini.</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="mx-auto w-full max-w-xl space-y-6">
                    <Card>
                        <CardHeader><CardTitle>Data Pengguna</CardTitle></CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-2">
                                <Label>Nama Lengkap</Label>
                                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Nama lengkap" />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Email</Label>
                                <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} placeholder="email@contoh.com" />
                                <InputError message={errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Telepon</Label>
                                <Input value={data.phone} onChange={(e) => setData('phone', e.target.value)} placeholder="+62812..." />
                                <InputError message={errors.phone} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Role</Label>
                                <Select value={data.role} onValueChange={(v) => setData('role', v)}>
                                    <SelectTrigger><SelectValue placeholder="Pilih role" /></SelectTrigger>
                                    <SelectContent>
                                        {roles.map((role) => (
                                            <SelectItem key={role.id} value={role.name}>{roleLabels[role.name] ?? role.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.role} />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label>Password</Label>
                                    <PasswordInput value={data.password} onChange={(e) => setData('password', e.target.value)} placeholder="Min 8 karakter" />
                                    <InputError message={errors.password} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Konfirmasi</Label>
                                    <PasswordInput value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} placeholder="Ulangi password" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <div className="flex gap-3">
                        <Button variant="outline" asChild className="flex-1"><Link href="/admin/users">Batal</Link></Button>
                        <Button type="submit" disabled={processing} className="flex-1">{processing && <Spinner />}Simpan</Button>
                    </div>
                </form>
            </div>
        </>
    );
}

UsersCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pengguna', href: '/admin/users' },
        { title: 'Tambah', href: '#' },
    ],
};
