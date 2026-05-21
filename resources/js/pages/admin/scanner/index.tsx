import { Head } from '@inertiajs/react';
import { Camera, Keyboard, ScanBarcode, Zap } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { QrScanner } from '@/components/scanner/qr-scanner';
import { playErrorSound, playSuccessSound } from '@/components/scanner/use-scan-sound';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';

type ScanMode = 'barcode' | 'camera' | 'manual';

type ScanLogItem = {
    id: number;
    name: string;
    status: string;
    success: boolean;
    time: string;
};

let logId = 0;

export default function ScannerIndex() {
    const [mode, setMode] = useState<ScanMode>('barcode');
    const [nis, setNis] = useState('');
    const [lastResult, setLastResult] = useState<{ success: boolean; message: string; student?: any } | null>(null);
    const [scanLog, setScanLog] = useState<ScanLogItem[]>([]);
    const [barcodeBuffer, setBarcodeBuffer] = useState('');
    const barcodeTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);
    const barcodeInputRef = useRef<HTMLInputElement>(null);

    const csrfToken = typeof document !== 'undefined'
        ? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || ''
        : '';

    const submitScan = useCallback(async (token: string) => {
        if (!token.trim()) return;
        try {
            const res = await fetch('/admin/scanner/scan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                body: JSON.stringify({ token: token.trim(), type: 'CHECK_IN' }),
            });
            const data = await res.json();

            setLastResult(data);
            setScanLog((prev) => [{
                id: ++logId,
                name: data.student?.full_name || token,
                status: data.success ? (data.student?.status || 'Hadir') : data.message,
                success: data.success,
                time: new Date().toLocaleTimeString('id-ID'),
            }, ...prev].slice(0, 50));

            if (data.success) {
                playSuccessSound(data.student?.full_name);
            } else {
                playErrorSound(data.message);
            }

            setTimeout(() => setLastResult(null), 2500);
        } catch {
            playErrorSound('Gagal menghubungi server');
            setLastResult({ success: false, message: 'Gagal menghubungi server.' });
            setTimeout(() => setLastResult(null), 2500);
        }
    }, [csrfToken]);

    // Barcode gun mode — listen for rapid keystrokes ending with Enter
    useEffect(() => {
        if (mode !== 'barcode') return;

        let buffer = '';
        let timer: ReturnType<typeof setTimeout> | null = null;

        function onKeyDown(e: KeyboardEvent) {
            // Ignore if user is typing in an input/textarea
            const tag = (e.target as HTMLElement)?.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;

            if (e.key === 'Enter') {
                e.preventDefault();
                if (buffer.length >= 3) {
                    submitScan(buffer);
                }
                buffer = '';
                setBarcodeBuffer('');
                return;
            }

            // Only accept printable characters
            if (e.key.length === 1) {
                buffer += e.key;
                setBarcodeBuffer(buffer);

                // Reset buffer after 100ms of no input (barcode guns type very fast)
                if (timer) clearTimeout(timer);
                timer = setTimeout(() => {
                    buffer = '';
                    setBarcodeBuffer('');
                }, 100);
            }
        }

        window.addEventListener('keydown', onKeyDown);
        return () => {
            window.removeEventListener('keydown', onKeyDown);
            if (timer) clearTimeout(timer);
        };
    }, [mode, submitScan]);

    // Auto-focus barcode hidden input for mobile barcode guns
    useEffect(() => {
        if (mode === 'barcode' && barcodeInputRef.current) {
            barcodeInputRef.current.focus();
        }
    }, [mode]);

    function handleManualSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (!nis.trim()) return;
        submitScan(nis.trim());
        setNis('');
    }

    function handleBarcodeInputSubmit(e: React.FormEvent) {
        e.preventDefault();
        const val = barcodeInputRef.current?.value?.trim();
        if (val && val.length >= 3) {
            submitScan(val);
        }
        if (barcodeInputRef.current) {
            barcodeInputRef.current.value = '';
        }
        setBarcodeBuffer('');
    }

    return (
        <>
            <Head title="Scanner QR Code" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Scanner Absensi</h1>
                    <p className="text-muted-foreground text-sm">
                        Pilih mode scanner sesuai perangkat yang digunakan.
                    </p>
                </div>

                {/* Mode Selector */}
                <div className="mx-auto flex w-full max-w-2xl gap-2">
                    <Button
                        variant={mode === 'barcode' ? 'default' : 'outline'}
                        className="flex-1"
                        onClick={() => setMode('barcode')}
                    >
                        <ScanBarcode className="mr-2 size-4" />
                        Barcode Gun
                    </Button>
                    <Button
                        variant={mode === 'camera' ? 'default' : 'outline'}
                        className="flex-1"
                        onClick={() => setMode('camera')}
                    >
                        <Camera className="mr-2 size-4" />
                        Kamera / Webcam
                    </Button>
                    <Button
                        variant={mode === 'manual' ? 'default' : 'outline'}
                        className="flex-1"
                        onClick={() => setMode('manual')}
                    >
                        <Keyboard className="mr-2 size-4" />
                        Input Manual
                    </Button>
                </div>

                <div className="mx-auto w-full max-w-2xl space-y-4">

                    {/* === BARCODE GUN MODE === */}
                    {mode === 'barcode' && (
                        <Card>
                            <CardHeader className="text-center">
                                <div className="bg-primary/10 text-primary mx-auto mb-2 flex size-16 items-center justify-center rounded-2xl">
                                    <ScanBarcode className="size-8" />
                                </div>
                                <CardTitle>Mode Barcode Scanner</CardTitle>
                                <CardDescription>
                                    Arahkan barcode gun ke QR Code siswa. Hasil scan otomatis terdeteksi.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="rounded-xl border-2 border-dashed border-primary/30 bg-primary/5 p-6 text-center">
                                    <Zap className="text-primary mx-auto mb-3 size-8" />
                                    <p className="text-sm font-semibold">Barcode Gun Siap</p>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        Klik area ini lalu scan — atau langsung scan, data otomatis masuk via keyboard.
                                    </p>
                                    {barcodeBuffer && (
                                        <div className="mt-3 font-mono text-lg font-bold tracking-wider text-primary">
                                            {barcodeBuffer}
                                            <span className="animate-pulse">|</span>
                                        </div>
                                    )}

                                    {/* Hidden input for barcode guns that need focus target */}
                                    <form onSubmit={handleBarcodeInputSubmit} className="mt-3">
                                        <input
                                            ref={barcodeInputRef}
                                            type="text"
                                            className="border-input mx-auto block w-full max-w-xs rounded-lg border bg-white/50 px-3 py-2 text-center font-mono text-sm opacity-60 focus:opacity-100 focus:outline-none focus:ring-2 focus:ring-primary/30"
                                            placeholder="Klik sini lalu scan..."
                                            autoFocus
                                            autoComplete="off"
                                        />
                                    </form>
                                </div>

                                {/* Result overlay */}
                                {lastResult && (
                                    <div className={`rounded-xl p-4 text-center ${
                                        lastResult.success
                                            ? 'border border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950'
                                            : 'border border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950'
                                    }`}>
                                        {lastResult.success ? (
                                            <>
                                                <p className="text-lg font-bold text-green-800 dark:text-green-200">{lastResult.student?.full_name}</p>
                                                <p className="text-sm text-green-600 dark:text-green-400">
                                                    {lastResult.student?.classroom} &middot; {lastResult.student?.status} &middot; {lastResult.student?.time}
                                                </p>
                                            </>
                                        ) : (
                                            <p className="font-semibold text-red-700 dark:text-red-300">{lastResult.message}</p>
                                        )}
                                    </div>
                                )}

                                <div className="text-muted-foreground space-y-1.5 text-xs">
                                    <p><b>Cara kerja:</b> Barcode gun mengirim data sebagai ketikan keyboard diakhiri Enter.</p>
                                    <p>Scanner mendeteksi ketikan cepat (&lt;100ms antar karakter) dan otomatis submit.</p>
                                    <p>Kompatibel: <b>iWare</b>, Honeywell, Zebra, Datalogic, dan semua HID barcode gun.</p>
                                </div>
                                <div className="mt-3 rounded-lg border border-blue-200 bg-blue-50 p-3 text-xs dark:border-blue-800 dark:bg-blue-950">
                                    <p className="mb-1.5 font-bold text-blue-800 dark:text-blue-200">Setup iWare Barcode Scanner:</p>
                                    <ol className="list-inside list-decimal space-y-0.5 text-blue-700 dark:text-blue-300">
                                        <li>Colok iWare ke USB — otomatis terdeteksi sebagai keyboard HID</li>
                                        <li>Pastikan iWare di-set ke mode <b>QR Code / 2D Barcode</b> (scan manual QR di buku panduan iWare)</li>
                                        <li>Set suffix ke <b>Enter/CR</b> (default iWare sudah Enter)</li>
                                        <li>Klik input field di atas, lalu arahkan iWare ke QR siswa</li>
                                    </ol>
                                    <p className="mt-1.5 text-blue-600 dark:text-blue-400">Tidak perlu install driver — plug and play di Windows/Mac/Linux.</p>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* === CAMERA MODE === */}
                    {mode === 'camera' && (
                        <QrScanner scanEndpoint="/admin/scanner/scan" scanType="CHECK_IN" />
                    )}

                    {/* === MANUAL MODE === */}
                    {mode === 'manual' && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Keyboard className="size-4" />
                                    Input Manual
                                </CardTitle>
                                <CardDescription>Ketik NIS atau QR token siswa secara manual.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <form onSubmit={handleManualSubmit} className="flex gap-2">
                                    <Input
                                        type="text"
                                        placeholder="NIS atau QR token..."
                                        value={nis}
                                        onChange={(e) => setNis(e.target.value)}
                                        className="flex-1"
                                        autoFocus
                                    />
                                    <Button type="submit" disabled={!nis.trim()}>Absen</Button>
                                </form>

                                {lastResult && (
                                    <div className={`rounded-xl p-4 text-center ${
                                        lastResult.success
                                            ? 'border border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950'
                                            : 'border border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950'
                                    }`}>
                                        {lastResult.success ? (
                                            <>
                                                <p className="text-lg font-bold text-green-800 dark:text-green-200">{lastResult.student?.full_name}</p>
                                                <p className="text-sm text-green-600 dark:text-green-400">
                                                    {lastResult.student?.classroom} &middot; {lastResult.student?.status}
                                                </p>
                                            </>
                                        ) : (
                                            <p className="font-semibold text-red-700 dark:text-red-300">{lastResult.message}</p>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {/* === SCAN LOG (shared across all modes) === */}
                    {scanLog.length > 0 && (
                        <Card>
                            <CardContent className="pt-6">
                                <h3 className="mb-3 text-sm font-semibold">Riwayat Scan Hari Ini ({scanLog.length})</h3>
                                <div className="max-h-64 space-y-2 overflow-y-auto">
                                    {scanLog.map((entry) => (
                                        <div
                                            key={entry.id}
                                            className={`flex items-center gap-3 rounded-lg border p-3 text-sm ${
                                                entry.success
                                                    ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950'
                                                    : 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950'
                                            }`}
                                        >
                                            <Badge variant={entry.success ? 'default' : 'destructive'} className="shrink-0">
                                                {entry.success ? 'OK' : 'GAGAL'}
                                            </Badge>
                                            <div className="min-w-0 flex-1">
                                                <p className="font-medium">{entry.name}</p>
                                                <p className="text-muted-foreground text-xs">{entry.status}</p>
                                            </div>
                                            <span className="text-muted-foreground shrink-0 text-xs">{entry.time}</span>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}
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
