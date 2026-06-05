import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, ExternalLink, Loader2, MessageSquare, Send, Trash2, XCircle } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type WaConfig = {
    id: string;
    display_phone: string;
    is_active: boolean;
    has_token: boolean;
} | null;

type Props = {
    config: WaConfig;
};

export default function WaConfigIndex({ config }: Props) {
    const [testPhone, setTestPhone] = useState('');
    const [testResult, setTestResult] = useState<{ success: boolean; message: string } | null>(null);
    const [testing, setTesting] = useState(false);

    const form = useForm({
        fonnte_token: '',
        display_phone: config?.display_phone ?? '',
        is_active: config?.is_active ?? false,
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        form.post('/admin/wa-config', {
            preserveScroll: true,
        });
    }

    async function handleTest() {
        if (!testPhone.trim()) return;
        setTesting(true);
        setTestResult(null);

        try {
            const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const res = await fetch('/admin/wa-config/test', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                body: JSON.stringify({ phone: testPhone }),
            });
            const data = await res.json();
            setTestResult(data);
        } catch {
            setTestResult({ success: false, message: 'Gagal menghubungi server.' });
        }

        setTesting(false);
    }

    function handleDelete() {
        if (!confirm('Hapus konfigurasi WhatsApp?')) return;
        router.delete('/admin/wa-config', { preserveScroll: true });
    }

    return (
        <>
            <Head title="WhatsApp Config" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Konfigurasi WhatsApp</h1>
                    <p className="text-muted-foreground text-sm">Setup notifikasi WhatsApp untuk absensi siswa via Fonnte.</p>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_380px]">
                    {/* Form */}
                    <div className="space-y-6">
                        {/* Status Card */}
                        <Card className={config?.is_active ? 'border-green-200 bg-green-50/50 dark:border-green-800 dark:bg-green-950/30' : ''}>
                            <CardContent className="flex items-center gap-4 pt-6">
                                {config?.is_active ? (
                                    <>
                                        <CheckCircle2 className="size-8 text-green-600" />
                                        <div>
                                            <p className="font-semibold text-green-800 dark:text-green-200">WhatsApp Aktif</p>
                                            <p className="text-sm text-green-600 dark:text-green-400">Nomor: {config.display_phone}</p>
                                        </div>
                                    </>
                                ) : (
                                    <>
                                        <XCircle className="text-muted-foreground size-8" />
                                        <div>
                                            <p className="font-semibold">Fonnte Belum Disetup</p>
                                            <p className="text-muted-foreground text-sm">Notifikasi dikirim via gateway default. Setup Fonnte untuk nomor WA khusus sekolah.</p>
                                        </div>
                                    </>
                                )}
                            </CardContent>
                        </Card>

                        {/* Config Form */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <MessageSquare className="size-4" />
                                    {config ? 'Update' : 'Setup'} Konfigurasi
                                </CardTitle>
                                <CardDescription>
                                    Masukkan token API dari Fonnte.com dan nomor WhatsApp yang terhubung.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleSubmit} className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label>Nomor WhatsApp</Label>
                                        <Input
                                            placeholder="08xxxxxxxxxx"
                                            value={form.data.display_phone}
                                            onChange={(e) => form.setData('display_phone', e.target.value)}
                                        />
                                        <p className="text-muted-foreground text-xs">Nomor WA yang terhubung ke Fonnte (scan QR).</p>
                                        {form.errors.display_phone && <p className="text-destructive text-sm">{form.errors.display_phone}</p>}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>Token API Fonnte</Label>
                                        <Input
                                            type="password"
                                            placeholder={config?.has_token ? '••••••••••••••••' : 'Paste token dari dashboard Fonnte'}
                                            value={form.data.fonnte_token}
                                            onChange={(e) => form.setData('fonnte_token', e.target.value)}
                                        />
                                        <p className="text-muted-foreground text-xs">
                                            Dapatkan dari{' '}
                                            <a href="https://fonnte.com" target="_blank" rel="noopener noreferrer" className="text-blue-600 underline">
                                                fonnte.com
                                            </a>{' '}
                                            setelah connect device.
                                        </p>
                                        {form.errors.fonnte_token && <p className="text-destructive text-sm">{form.errors.fonnte_token}</p>}
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="wa_active"
                                            checked={form.data.is_active}
                                            onCheckedChange={(c) => form.setData('is_active', c === true)}
                                        />
                                        <Label htmlFor="wa_active">Aktifkan notifikasi WhatsApp</Label>
                                    </div>

                                    <div className="flex gap-2">
                                        <Button type="submit" disabled={form.processing}>
                                            {form.processing && <Loader2 className="mr-1.5 size-4 animate-spin" />}
                                            Simpan
                                        </Button>
                                        {config && (
                                            <Button type="button" variant="outline" className="text-red-600" onClick={handleDelete}>
                                                <Trash2 className="mr-1.5 size-4" />
                                                Hapus
                                            </Button>
                                        )}
                                    </div>
                                </form>
                            </CardContent>
                        </Card>

                        {/* Test Message */}
                        {config?.has_token && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <Send className="size-4" />
                                        Test Kirim Pesan
                                    </CardTitle>
                                    <CardDescription>Kirim pesan test untuk memastikan konfigurasi benar.</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div className="flex gap-2">
                                        <Input
                                            placeholder="Nomor tujuan (08xxx)"
                                            value={testPhone}
                                            onChange={(e) => setTestPhone(e.target.value)}
                                            className="flex-1"
                                        />
                                        <Button onClick={handleTest} disabled={testing || !testPhone.trim()}>
                                            {testing ? <Loader2 className="size-4 animate-spin" /> : <Send className="size-4" />}
                                            Test
                                        </Button>
                                    </div>
                                    {testResult && (
                                        <div className={`rounded-lg p-3 text-sm ${testResult.success ? 'bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-200' : 'bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200'}`}>
                                            {testResult.message}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Guide Panel */}
                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="text-base">Panduan Setup Fonnte</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ol className="space-y-3 text-sm">
                                <li className="flex gap-2">
                                    <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">1</span>
                                    <span>
                                        Buka{' '}
                                        <a href="https://fonnte.com" target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 text-blue-600 underline">
                                            fonnte.com <ExternalLink className="size-3" />
                                        </a>{' '}
                                        dan daftar akun.
                                    </span>
                                </li>
                                <li className="flex gap-2">
                                    <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">2</span>
                                    <span>Login, klik <strong>"Tambah Device"</strong>.</span>
                                </li>
                                <li className="flex gap-2">
                                    <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">3</span>
                                    <span>Scan <strong>QR Code</strong> dengan WhatsApp di HP. Gunakan nomor khusus (bukan pribadi).</span>
                                </li>
                                <li className="flex gap-2">
                                    <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">4</span>
                                    <span>Setelah connected, copy <strong>Token API</strong> dari dashboard Fonnte.</span>
                                </li>
                                <li className="flex gap-2">
                                    <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">5</span>
                                    <span>Paste Token di form sebelah kiri, isi nomor WA, lalu <strong>Simpan</strong>.</span>
                                </li>
                                <li className="flex gap-2">
                                    <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">6</span>
                                    <span>Klik <strong>"Test Kirim"</strong> untuk verifikasi setup berhasil.</span>
                                </li>
                                <li className="flex gap-2">
                                    <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">7</span>
                                    <span>Centang <strong>"Aktifkan"</strong> untuk mulai kirim notifikasi absensi.</span>
                                </li>
                            </ol>

                            <div className="mt-4 rounded-lg border bg-amber-50 p-3 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                <p className="mb-1 font-semibold">Tips Keamanan:</p>
                                <ul className="list-inside list-disc space-y-0.5">
                                    <li>Gunakan nomor WA khusus, bukan nomor pribadi</li>
                                    <li>Jangan kirim spam atau broadcast massal</li>
                                    <li>Notifikasi absensi aman (1 pesan per siswa per event)</li>
                                    <li>Pilih paket Fonnte sesuai jumlah siswa</li>
                                </ul>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

WaConfigIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'WhatsApp', href: '/admin/wa-config' },
    ],
};
