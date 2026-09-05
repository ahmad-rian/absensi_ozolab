import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, Copy, ExternalLink, RefreshCw, ScanLine, Tv } from 'lucide-react';
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

type SchoolData = {
    id: string; name: string; address: string | null; city: string | null; phone: string | null;
    email: string | null; website: string | null; is_active: boolean; scanner_token: string;
    /** Alias pilihan sendiri. Kosong berarti memakai potongan token. */
    scan_short_code: string | null;
    /** Kode yang berlaku sekarang — alias kalau ada, potongan token kalau belum. */
    scan_short_code_effective: string;
};

export default function SchoolsEdit({ school }: { school: SchoolData }) {
    const { data, setData, put, processing, errors } = useForm({
        name: school.name, address: school.address ?? '', city: school.city ?? '',
        phone: school.phone ?? '', email: school.email ?? '', website: school.website ?? '',
        is_active: school.is_active, scan_short_code: school.scan_short_code ?? '',
    });

    const [copied, setCopied] = useState(false);
    const [shortCopied, setShortCopied] = useState(false);
    const [regenerating, setRegenerating] = useState(false);
    const origin = typeof window !== 'undefined' ? window.location.origin : '';
    const scanUrl = `${origin}/scan/${school.scanner_token}`;
    // Pratinjau ikut apa yang sedang diketik; kalau dikosongkan, kembali ke kode
    // yang berlaku sekarang supaya tidak pernah menampilkan alamat kosong.
    const shortUrl = `${origin}/g/${data.scan_short_code.trim() || school.scan_short_code_effective}`;

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

    async function copyShortLink() {
        try {
            await navigator.clipboard.writeText(shortUrl);
            setShortCopied(true);
            toast.success('Link scan ringan disalin.');
            setTimeout(() => setShortCopied(false), 2000);
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
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2"><Tv className="size-5" /> Link Scan Ringan</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <p className="text-muted-foreground text-sm">
                                Halaman scan sederhana untuk perangkat gerbang berspesifikasi rendah — box Android TV,
                                tablet lama. Tanpa kamera, hanya barcode gun dan pembaca RFID. Alamatnya dibuat pendek
                                karena diketik memakai remote.
                            </p>
                            <div className="grid gap-2">
                                <Label htmlFor="scan_short_code">Nama Link</Label>
                                <div className="flex items-center gap-2">
                                    <span className="text-muted-foreground shrink-0 font-mono text-sm">{origin}/g/</span>
                                    <Input
                                        id="scan_short_code"
                                        value={data.scan_short_code}
                                        onChange={(e) => setData('scan_short_code', e.target.value)}
                                        placeholder={school.scan_short_code_effective}
                                        className="font-mono text-sm"
                                    />
                                </div>
                                <InputError message={errors.scan_short_code} />
                                <p className="text-muted-foreground text-xs">
                                    Huruf kecil, angka, strip, dan garis bawah. Minimal 3 karakter. Kosongkan untuk
                                    kembali memakai kode bawaan{' '}
                                    <span className="font-mono">{school.scanner_token.slice(0, 8)}</span>.
                                </p>
                            </div>
                            <div className="flex gap-2">
                                <Input value={shortUrl} readOnly onFocus={(e) => e.target.select()} className="font-mono text-sm" />
                                <Button type="button" variant="outline" size="icon" onClick={copyShortLink} title="Salin link">
                                    {shortCopied ? <Check className="size-4 text-emerald-600" /> : <Copy className="size-4" />}
                                </Button>
                                <Button type="button" variant="outline" size="icon" asChild title="Buka link">
                                    <a href={shortUrl} target="_blank" rel="noopener noreferrer"><ExternalLink className="size-4" /></a>
                                </Button>
                            </div>
                            <p className="text-muted-foreground text-xs">
                                Nama yang gampang ditebak berarti halaman ini gampang dibuka orang. Karena itu halaman
                                ringan tidak memuat token sekolah sama sekali — gerbang sholat dan perpustakaan tetap
                                terlindungi walau nama ini tersebar.
                            </p>
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
