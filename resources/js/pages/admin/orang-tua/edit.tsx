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

type ParentData = {
    id: number;
    whatsapp_number: string;
    relation: string;
    nik: string | null;
    occupation: string | null;
    address: string | null;
    city: string | null;
    user: { id: number; name: string; email: string; phone: string | null };
};

export default function OrangTuaEdit({ parent }: { parent: ParentData }) {
    const { data, setData, put, processing, errors } = useForm({
        name: parent.user.name,
        email: parent.user.email,
        phone: parent.user.phone ?? parent.whatsapp_number,
        relation: parent.relation,
        nik: parent.nik ?? '',
        occupation: parent.occupation ?? '',
        address: parent.address ?? '',
        city: parent.city ?? '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/admin/orang-tua/${parent.id}`);
    }

    return (
        <>
            <Head title="Edit Orang Tua" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <Link href="/admin/orang-tua"><ArrowLeft className="size-4" /></Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Edit Orang Tua</h1>
                        <p className="text-muted-foreground text-sm">Perbarui data {parent.user.name}.</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="mx-auto w-full max-w-2xl space-y-6">
                    <Card>
                        <CardHeader><CardTitle>Data Akun</CardTitle></CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2 sm:col-span-2">
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
                                <Label>No. WhatsApp</Label>
                                <div className="flex">
                                    <span className="border-input bg-muted text-muted-foreground inline-flex items-center rounded-l-md border border-r-0 px-3 text-sm">+62</span>
                                    <Input value={data.phone} onChange={(e) => setData('phone', e.target.value)} className="rounded-l-none" />
                                </div>
                                <InputError message={errors.phone} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Hubungan</Label>
                                <Select value={data.relation} onValueChange={(v) => setData('relation', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="AYAH">Ayah</SelectItem>
                                        <SelectItem value="IBU">Ibu</SelectItem>
                                        <SelectItem value="WALI">Wali</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.relation} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>Data Tambahan</CardTitle></CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label>NIK</Label>
                                <Input value={data.nik} onChange={(e) => setData('nik', e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Pekerjaan</Label>
                                <Input value={data.occupation} onChange={(e) => setData('occupation', e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Kota</Label>
                                <Input value={data.city} onChange={(e) => setData('city', e.target.value)} />
                            </div>
                            <div className="grid gap-2 sm:col-span-2">
                                <Label>Alamat</Label>
                                <Textarea value={data.address} onChange={(e) => setData('address', e.target.value)} rows={2} />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex gap-3">
                        <Button variant="outline" asChild className="flex-1"><Link href="/admin/orang-tua">Batal</Link></Button>
                        <Button type="submit" disabled={processing} className="flex-1">
                            {processing && <Spinner />}
                            Simpan Perubahan
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

OrangTuaEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Orang Tua', href: '/admin/orang-tua' },
        { title: 'Edit', href: '#' },
    ],
};
