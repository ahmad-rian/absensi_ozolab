import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';

type SchoolData = { id: number; name: string; address: string | null; city: string | null; phone: string | null; email: string | null; website: string | null; is_active: boolean };

export default function SchoolsEdit({ school }: { school: SchoolData }) {
    const { data, setData, put, processing, errors } = useForm({
        name: school.name, address: school.address ?? '', city: school.city ?? '',
        phone: school.phone ?? '', email: school.email ?? '', website: school.website ?? '',
        is_active: school.is_active,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/admin/schools/${school.id}`);
    }

    return (
        <>
            <Head title="Edit Sekolah" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild><Link href="/admin/schools"><ArrowLeft className="size-4" /></Link></Button>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Edit Sekolah</h1>
                        <p className="text-muted-foreground text-sm">Perbarui data {school.name}.</p>
                    </div>
                </div>
                <form onSubmit={handleSubmit} className="mx-auto w-full max-w-xl space-y-6">
                    <Card>
                        <CardHeader><CardTitle>Data Sekolah</CardTitle></CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-2">
                                <Label>Nama Sekolah *</Label>
                                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label>Kota</Label>
                                    <Input value={data.city} onChange={(e) => setData('city', e.target.value)} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Telepon</Label>
                                    <Input value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label>Email</Label>
                                    <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                                    <InputError message={errors.email} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Website</Label>
                                    <Input value={data.website} onChange={(e) => setData('website', e.target.value)} />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label>Alamat</Label>
                                <Textarea value={data.address} onChange={(e) => setData('address', e.target.value)} rows={2} />
                            </div>
                            <div className="flex items-center gap-2.5">
                                <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(c) => setData('is_active', Boolean(c))} />
                                <Label htmlFor="is_active" className="font-normal">Sekolah Aktif</Label>
                            </div>
                        </CardContent>
                    </Card>
                    <div className="flex gap-3">
                        <Button variant="outline" asChild className="flex-1"><Link href="/admin/schools">Batal</Link></Button>
                        <Button type="submit" disabled={processing} className="flex-1">{processing && <Spinner />}Simpan Perubahan</Button>
                    </div>
                </form>
            </div>
        </>
    );
}

SchoolsEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Sekolah', href: '/admin/schools' },
        { title: 'Edit', href: '#' },
    ],
};
