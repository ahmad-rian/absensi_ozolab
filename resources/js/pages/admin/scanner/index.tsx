import { Head } from '@inertiajs/react';
import { Keyboard, Search } from 'lucide-react';
import { useState } from 'react';
import { QrScanner } from '@/components/scanner/qr-scanner';
import { playErrorSound, playSuccessSound } from '@/components/scanner/use-scan-sound';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';

export default function ScannerIndex() {
    const [nis, setNis] = useState('');
    const [manualResult, setManualResult] = useState<string | null>(null);

    async function handleManualScan(e: React.FormEvent) {
        e.preventDefault();
        if (!nis.trim()) return;
        const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
        try {
            const res = await fetch('/admin/scanner/scan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
                body: JSON.stringify({ token: nis.trim(), type: 'CHECK_IN' }),
            });
            const data = await res.json();
            if (data.success) {
                playSuccessSound();
                setManualResult(`${data.student.full_name} — ${data.student.status}`);
            } else {
                playErrorSound();
                setManualResult(data.message);
            }
        } catch {
            playErrorSound();
            setManualResult('Gagal menghubungi server.');
        }
        setNis('');
        setTimeout(() => setManualResult(null), 3000);
    }

    return (
        <>
            <Head title="Scanner QR Code" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Scanner QR Code</h1>
                    <p className="text-muted-foreground text-sm">
                        Kamera langsung aktif. Siswa tinggal bergantian scan QR Code — tidak perlu reload.
                    </p>
                </div>

                <div className="mx-auto w-full max-w-2xl">
                    <QrScanner scanEndpoint="/admin/scanner/scan" scanType="CHECK_IN" />
                </div>

                <div className="mx-auto w-full max-w-2xl">
                    {/* Manual NIS fallback */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Keyboard className="size-4" />
                                Input Manual NIS
                            </CardTitle>
                            <CardDescription>Jika QR Code tidak terbaca, masukkan NIS siswa.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleManualScan} className="flex gap-2">
                                <Input
                                    type="text"
                                    placeholder="Masukkan NIS siswa..."
                                    value={nis}
                                    onChange={(e) => setNis(e.target.value)}
                                    className="flex-1"
                                />
                                <Button type="submit" disabled={!nis.trim()}>
                                    <Search className="mr-2 size-4" />
                                    Cari
                                </Button>
                            </form>
                            {manualResult && (
                                <p className="text-muted-foreground mt-2 text-sm">{manualResult}</p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

ScannerIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Scanner', href: '/admin/scanner' },
    ],
};
