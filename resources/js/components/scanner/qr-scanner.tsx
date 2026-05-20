import { Html5Qrcode, Html5QrcodeScanner } from 'html5-qrcode';
import { Camera, CheckCircle2, Loader2, RefreshCw, XCircle } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { playErrorSound, playSuccessSound } from '@/components/scanner/use-scan-sound';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type ScanResult = {
    success: boolean;
    message: string;
    student?: {
        full_name: string;
        nis: string;
        classroom: string | null;
        status: string;
        type: string;
        time: string;
    } | null;
};

type ScanLogEntry = ScanResult & { id: number; timestamp: string };

type CameraDevice = { id: string; label: string };

interface QrScannerProps {
    scanEndpoint: string;
    scanType?: 'CHECK_IN' | 'CHECK_OUT';
    extraPayload?: Record<string, unknown>;
}

let activeScannerId: string | null = null;

export function QrScanner({ scanEndpoint, scanType = 'CHECK_IN', extraPayload = {} }: QrScannerProps) {
    const [status, setStatus] = useState<'loading' | 'scanning' | 'error'>('loading');
    const [error, setError] = useState<string | null>(null);
    const [lastResult, setLastResult] = useState<ScanResult | null>(null);
    const [scanLog, setScanLog] = useState<ScanLogEntry[]>([]);
    const [cameras, setCameras] = useState<CameraDevice[]>([]);
    const [selectedCamera, setSelectedCamera] = useState<string>('');

    const cooldownRef = useRef(false);
    const logIdRef = useRef(0);
    const mountedRef = useRef(true);
    const scannerRef = useRef<Html5Qrcode | null>(null);
    const readerId = `qr-reader-${useId().replace(/:/g, '')}`;
    const scanTypeRef = useRef(scanType);
    const scanEndpointRef = useRef(scanEndpoint);
    const extraPayloadRef = useRef(extraPayload);

    scanTypeRef.current = scanType;
    scanEndpointRef.current = scanEndpoint;
    extraPayloadRef.current = extraPayload;

    const csrfRef = useRef(
        typeof document !== 'undefined'
            ? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || ''
            : '',
    );

    async function processResult(decodedText: string) {
        if (cooldownRef.current || !mountedRef.current) return;
        cooldownRef.current = true;

        try {
            const res = await fetch(scanEndpointRef.current, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfRef.current,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ token: decodedText, type: scanTypeRef.current, ...extraPayloadRef.current }),
            });
            const data: ScanResult = await res.json();
            if (!mountedRef.current) return;

            setLastResult(data);
            setScanLog((prev) => [{ ...data, id: ++logIdRef.current, timestamp: new Date().toLocaleTimeString('id-ID') }, ...prev].slice(0, 50));

            if (data.success) playSuccessSound(data.student?.full_name);
            else playErrorSound(data.message);
        } catch {
            if (!mountedRef.current) return;
            setLastResult({ success: false, message: 'Gagal menghubungi server.' });
            playErrorSound('Gagal menghubungi server');
        }

        setTimeout(() => {
            cooldownRef.current = false;
            if (mountedRef.current) setLastResult(null);
        }, 1800);
    }

    async function stopScanner() {
        if (scannerRef.current) {
            try { if (scannerRef.current.isScanning) await scannerRef.current.stop(); } catch {}
            try { scannerRef.current.clear(); } catch {}
            scannerRef.current = null;
        }
    }

    async function startWithCamera(cameraId: string) {
        setStatus('loading');
        setError(null);

        await stopScanner();

        const el = document.getElementById(readerId);
        if (!el) { setError('Scanner element not found.'); setStatus('error'); return; }
        el.innerHTML = '';
        activeScannerId = readerId;

        try {
            const scanner = new Html5Qrcode(readerId);
            scannerRef.current = scanner;

            await scanner.start(
                cameraId ? { deviceId: { exact: cameraId } } : { facingMode: 'environment' },
                {
                    fps: 15,
                    qrbox: { width: 280, height: 280 },
                    aspectRatio: 4 / 3,
                    disableFlip: false,
                    experimentalFeatures: { useBarCodeDetectorIfSupported: true },
                },
                (text) => processResult(text),
                () => {},
            );
            if (mountedRef.current) setStatus('scanning');
        } catch (err: any) {
            if (!mountedRef.current) return;
            const msg = err?.message || String(err);
            if (msg.includes('Permission') || msg.includes('NotAllowed')) {
                setError('Akses kamera ditolak. Izinkan akses kamera di pengaturan browser.');
            } else if (msg.includes('NotFound') || msg.includes('not found')) {
                setError('Kamera tidak ditemukan pada perangkat ini.');
            } else {
                setError(`Gagal memulai kamera: ${msg}`);
            }
            setStatus('error');
        }
    }

    // Load available cameras + auto-start
    useEffect(() => {
        mountedRef.current = true;

        async function init() {
            await new Promise((r) => setTimeout(r, 200));
            if (!mountedRef.current) return;

            try {
                const devices = await Html5Qrcode.getCameras();
                if (!mountedRef.current) return;

                const cams = devices.map((d) => ({ id: d.id, label: d.label || `Camera ${d.id.slice(0, 8)}` }));
                setCameras(cams);

                // Prefer external/USB camera (usually last in list or has "USB"/"iWare" in name)
                const external = cams.find((c) =>
                    /usb|iware|external|back|rear|hd|web/i.test(c.label),
                );
                const pick = external || cams[cams.length - 1] || cams[0];

                if (pick) {
                    setSelectedCamera(pick.id);
                    await startWithCamera(pick.id);
                } else {
                    // No cameras — try facingMode fallback
                    await startWithCamera('');
                }
            } catch (err: any) {
                if (!mountedRef.current) return;
                setError('Tidak bisa mengakses daftar kamera: ' + (err?.message || ''));
                setStatus('error');
            }
        }

        init();

        return () => {
            mountedRef.current = false;
            activeScannerId = null;
            stopScanner();
        };
    }, []);

    async function switchCamera(cameraId: string) {
        setSelectedCamera(cameraId);
        await startWithCamera(cameraId);
    }

    return (
        <div className="space-y-4">
            {/* Camera selector */}
            {cameras.length > 1 && (
                <div className="flex items-center gap-2">
                    <Camera className="text-muted-foreground size-4 shrink-0" />
                    <select
                        value={selectedCamera}
                        onChange={(e) => switchCamera(e.target.value)}
                        className="border-input bg-background flex-1 rounded-lg border px-3 py-2 text-sm"
                    >
                        {cameras.map((cam) => (
                            <option key={cam.id} value={cam.id}>
                                {cam.label}
                            </option>
                        ))}
                    </select>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => startWithCamera(selectedCamera)}
                        title="Restart kamera"
                    >
                        <RefreshCw className="size-3.5" />
                    </Button>
                </div>
            )}

            <Card className="overflow-hidden">
                <CardContent className="p-0">
                    <div className="relative">
                        <div id={readerId} className="mx-auto max-w-lg" style={{ minHeight: 320 }} />

                        {status === 'loading' && (
                            <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-zinc-900">
                                <Loader2 className="size-10 animate-spin text-zinc-400" />
                                <p className="text-sm text-zinc-400">Meminta akses kamera...</p>
                            </div>
                        )}

                        {status === 'error' && (
                            <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-zinc-900 px-6">
                                <XCircle className="size-10 text-red-400" />
                                <p className="text-center text-sm text-red-300">{error}</p>
                                <Button variant="outline" size="sm" onClick={() => startWithCamera(selectedCamera)} className="mt-2">
                                    <RefreshCw className="mr-1.5 size-3.5" />
                                    Coba Lagi
                                </Button>
                            </div>
                        )}

                        {lastResult && (
                            <div className="absolute inset-0 z-10 flex items-center justify-center bg-black/70">
                                <div className="mx-4 w-full max-w-xs rounded-2xl bg-white p-6 text-center shadow-2xl dark:bg-zinc-900">
                                    {lastResult.success ? (
                                        <>
                                            <CheckCircle2 className="mx-auto size-14 text-green-500" />
                                            <p className="mt-3 text-lg font-bold">{lastResult.student?.full_name}</p>
                                            <p className="text-muted-foreground text-sm">
                                                {lastResult.student?.classroom} &middot; {lastResult.student?.nis}
                                            </p>
                                            <Badge className="mt-2 bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                {lastResult.student?.status} &middot; {lastResult.student?.time}
                                            </Badge>
                                        </>
                                    ) : (
                                        <>
                                            <XCircle className="mx-auto size-14 text-red-500" />
                                            <p className="mt-3 text-sm font-semibold text-red-600 dark:text-red-400">{lastResult.message}</p>
                                        </>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>

            {scanLog.length > 0 && (
                <Card>
                    <CardContent className="pt-6">
                        <h3 className="mb-3 text-sm font-semibold">Riwayat Scan ({scanLog.length})</h3>
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
                                    {entry.success ? (
                                        <CheckCircle2 className="size-5 shrink-0 text-green-600" />
                                    ) : (
                                        <XCircle className="size-5 shrink-0 text-red-500" />
                                    )}
                                    <div className="min-w-0 flex-1">
                                        {entry.student ? (
                                            <p className="font-medium">
                                                {entry.student.full_name}
                                                <span className="text-muted-foreground font-normal"> — {entry.student.classroom}</span>
                                            </p>
                                        ) : (
                                            <p className="text-red-600 dark:text-red-400">{entry.message}</p>
                                        )}
                                    </div>
                                    <span className="text-muted-foreground shrink-0 text-xs">{entry.timestamp}</span>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
