import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, Copy, ExternalLink, RefreshCw, ScanLine } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';

type SchoolData = { id: string; name: string; address: string | null; city: string | null; phone: string | null; email: string | null; website: string | null; is_active: boolean; scanner_token: string };

export default function SchoolsEdit({ school }: { school: SchoolData }) {
    const { data, setData, put, processing, errors } = useForm({
        name: school.name, address: school.address ?? '', city: school.city ?? '',
        phone: school.phone ?? '', email: school.email ?? '', website: school.website ?? '',
        is_active: school.is_active,
    });

    const [copied, setCopied] = useState(false);
    const [regenerating, setRegenerating] = useState(false);
    const scanUrl = typeof window !== 'undefined' ? `${window.location.origin}/scan/${school.scanner_token}` : `/scan/${school.scanner_token}`;

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/admin/schools/${school.id}`);
    }

    async function copyLink() {
        try {
            await navigator.clipboard.writeText(scanUrl);
            setCopied(true);
            toast.success('Link scan disalin.');
            setTimeout(() => setCopied(false), 2000);
        } catch {
            toast.error('Gagal menyalin link.');
        }
    }

    function regenerateLink() {
        if (!confirm('Buat ulang link scan? Link lama akan langsung tidak berlaku.')) return;
        router.post(`/admin/schools/${school.id}/scanner-token`, {}, {
            preserveScroll: true,
            onStart: () => setRegenerating(true),
            onFinish: () => setRegenerating(false),
        });
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
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2"><ScanLine className="size-5" /> Link Absensi Publik</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <p className="text-muted-foreground text-sm">
                                Bagikan link ini untuk halaman scan absensi siswa. Tidak perlu login — cukup buka & scan QR.
                            </p>
                            <div className="flex gap-2">
                                <Input value={scanUrl} readOnly onFocus={(e) => e.target.select()} className="font-mono text-sm" />
                                <Button type="button" variant="outline" size="icon" onClick={copyLink} title="Salin link">
                                    {copied ? <Check className="size-4 text-emerald-600" /> : <Copy className="size-4" />}
                                </Button>
                                <Button type="button" variant="outline" size="icon" asChild title="Buka link">
                                    <a href={scanUrl} target="_blank" rel="noopener noreferrer"><ExternalLink className="size-4" /></a>
                                </Button>
                            </div>
                            <div>
                                <Button type="button" variant="ghost" size="sm" onClick={regenerateLink} disabled={regenerating} className="text-destructive hover:text-destructive">
                                    {regenerating ? <Spinner /> : <RefreshCw className="size-4" />} Buat Ulang Link
                                </Button>
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
