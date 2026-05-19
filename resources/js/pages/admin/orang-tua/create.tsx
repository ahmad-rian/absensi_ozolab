import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';

export default function OrangTuaCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        phone: '',
        relation: '',
        password: '',
        password_confirmation: '',
        nik: '',
        occupation: '',
        address: '',
        city: '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post('/admin/orang-tua');
    }

    return (
        <>
            <Head title="Tambah Orang Tua" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <Link href="/admin/orang-tua"><ArrowLeft className="size-4" /></Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Tambah Orang Tua</h1>
                        <p className="text-muted-foreground text-sm">Daftarkan akun orang tua/wali baru.</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="mx-auto w-full max-w-2xl space-y-6">
                    <Card>
                        <CardHeader><CardTitle>Data Akun</CardTitle></CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2 sm:col-span-2">
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
                                <Label>No. WhatsApp</Label>
                                <div className="flex">
                                    <span className="border-input bg-muted text-muted-foreground inline-flex items-center rounded-l-md border border-r-0 px-3 text-sm">+62</span>
                                    <Input value={data.phone} onChange={(e) => setData('phone', e.target.value)} className="rounded-l-none" placeholder="812xxxxxxxx" />
                                </div>
                                <InputError message={errors.phone} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Hubungan</Label>
                                <Select value={data.relation} onValueChange={(v) => setData('relation', v)}>
                                    <SelectTrigger><SelectValue placeholder="Pilih hubungan" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="AYAH">Ayah</SelectItem>
                                        <SelectItem value="IBU">Ibu</SelectItem>
                                        <SelectItem value="WALI">Wali</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.relation} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Password</Label>
                                <PasswordInput value={data.password} onChange={(e) => setData('password', e.target.value)} placeholder="Min 8 karakter" />
                                <InputError message={errors.password} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Konfirmasi Password</Label>
                                <PasswordInput value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} placeholder="Ulangi password" />
                                <InputError message={errors.password_confirmation} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>Data Tambahan (Opsional)</CardTitle></CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label>NIK</Label>
                                <Input value={data.nik} onChange={(e) => setData('nik', e.target.value)} placeholder="16 digit NIK" />
                            </div>
                            <div className="grid gap-2">
                                <Label>Pekerjaan</Label>
                                <Input value={data.occupation} onChange={(e) => setData('occupation', e.target.value)} placeholder="Pekerjaan" />
                            </div>
                            <div className="grid gap-2">
                                <Label>Kota</Label>
                                <Input value={data.city} onChange={(e) => setData('city', e.target.value)} placeholder="Kota" />
                            </div>
                            <div className="grid gap-2 sm:col-span-2">
                                <Label>Alamat</Label>
                                <Textarea value={data.address} onChange={(e) => setData('address', e.target.value)} placeholder="Alamat lengkap" rows={2} />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex gap-3">
                        <Button variant="outline" asChild className="flex-1"><Link href="/admin/orang-tua">Batal</Link></Button>
                        <Button type="submit" disabled={processing} className="flex-1">
                            {processing && <Spinner />}
                            Simpan
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

OrangTuaCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Orang Tua', href: '/admin/orang-tua' },
        { title: 'Tambah', href: '#' },
    ],
};
