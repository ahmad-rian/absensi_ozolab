import { Html5Qrcode } from 'html5-qrcode';
import { CheckCircle2, Loader2, XCircle } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { playErrorSound, playSuccessSound } from '@/components/scanner/use-scan-sound';
import { Badge } from '@/components/ui/badge';
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

interface QrScannerProps {
    scanEndpoint: string;
    scanType?: 'CHECK_IN' | 'CHECK_OUT';
    extraPayload?: Record<string, unknown>;
}

// Module-level tracker to prevent double-init across StrictMode remounts
let activeScannerId: string | null = null;

export function QrScanner({ scanEndpoint, scanType = 'CHECK_IN', extraPayload = {} }: QrScannerProps) {
    const [status, setStatus] = useState<'loading' | 'scanning' | 'error'>('loading');
    const [error, setError] = useState<string | null>(null);
    const [lastResult, setLastResult] = useState<ScanResult | null>(null);
    const [scanLog, setScanLog] = useState<ScanLogEntry[]>([]);

    const cooldownRef = useRef(false);
    const logIdRef = useRef(0);
    const mountedRef = useRef(true);
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

    useEffect(() => {
        mountedRef.current = true;
        let scanner: Html5Qrcode | null = null;
        let aborted = false;

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

                if (data.success) playSuccessSound();
                else playErrorSound();
            } catch {
                if (!mountedRef.current) return;
                setLastResult({ success: false, message: 'Gagal menghubungi server.' });
                playErrorSound();
            }

            setTimeout(() => {
                cooldownRef.current = false;
                if (mountedRef.current) setLastResult(null);
            }, 2500);
        }

        async function start() {
            // Prevent double scanner from StrictMode
            if (activeScannerId && activeScannerId !== readerId) return;

            await new Promise((r) => setTimeout(r, 200));
            if (aborted || !mountedRef.current) return;

            const el = document.getElementById(readerId);
            if (!el) { setError('Scanner element not found.'); setStatus('error'); return; }

            // Clean any leftover DOM from previous scanner
            el.innerHTML = '';
            activeScannerId = readerId;

            try {
                scanner = new Html5Qrcode(readerId);
                await scanner.start(
                    { facingMode: 'environment' },
                    { fps: 8, qrbox: { width: 250, height: 250 }, aspectRatio: 1 },
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

        start();

        return () => {
            aborted = true;
            mountedRef.current = false;
            activeScannerId = null;
            if (scanner) {
                const s = scanner;
                scanner = null;
                (async () => {
                    try { if (s.isScanning) await s.stop(); } catch {}
                    try { s.clear(); } catch {}
                })();
            }
        };
    }, []);

    return (
        <div className="space-y-4">
            <Card className="overflow-hidden">
                <CardContent className="p-0">
                    <div className="relative">
                        <div id={readerId} className="mx-auto max-w-md" style={{ minHeight: 300 }} />

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
