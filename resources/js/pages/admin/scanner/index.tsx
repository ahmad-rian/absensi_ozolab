import { Head } from '@inertiajs/react';
import { Camera, CheckCircle2, Keyboard, ScanBarcode, ShieldCheck, UserCheck, Zap } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { QrScanner } from '@/components/scanner/qr-scanner';
import { playErrorSound, playSuccessSound } from '@/components/scanner/use-scan-sound';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';

type ScanMode = 'barcode' | 'camera' | 'manual';
type ScanPurpose = 'attendance' | 'validate';

type ScanLogItem = {
    id: number;
    name: string;
    status: string;
    success: boolean;
    time: string;
    mode: string;
};

type ValidateResult = {
    full_name: string;
    nis: string | null;
    nisn: string | null;
    no_absen: string | null;
    classroom: string | null;
    gender: string | null;
    photo_url: string | null;
    is_active: boolean;
    has_qr: boolean;
};

let logId = 0;

export default function ScannerIndex() {
    const [mode, setMode] = useState<ScanMode>('barcode');
    const [purpose, setPurpose] = useState<ScanPurpose>('attendance');
    const [nis, setNis] = useState('');
    const [lastResult, setLastResult] = useState<{ success: boolean; message: string; student?: any; mode?: string } | null>(null);
    const [validateResult, setValidateResult] = useState<ValidateResult | null>(null);
    const [scanLog, setScanLog] = useState<ScanLogItem[]>([]);
    const [barcodeBuffer, setBarcodeBuffer] = useState('');
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
                body: JSON.stringify({ token: token.trim(), type: 'CHECK_IN', mode: purpose }),
            });
            const data = await res.json();

            if (data.mode === 'validate' && data.success) {
                setValidateResult(data.student);
                playSuccessSound(data.student?.full_name);
            } else {
                setValidateResult(null);
                setLastResult(data);

                setScanLog((prev) => [{
                    id: ++logId,
                    name: data.student?.full_name || token,
                    status: data.success
                        ? (data.mode === 'validate' ? 'Valid' : (data.student?.status || 'Hadir'))
                        : data.message,
                    success: data.success,
                    time: new Date().toLocaleTimeString('id-ID'),
                    mode: data.mode || purpose,
                }, ...prev].slice(0, 50));

                if (data.success) {
                    playSuccessSound(data.student?.full_name);
                } else {
                    playErrorSound(data.message);
                }

                setTimeout(() => setLastResult(null), 2500);
            }
        } catch {
            playErrorSound('Gagal menghubungi server');
            setLastResult({ success: false, message: 'Gagal menghubungi server.' });
            setTimeout(() => setLastResult(null), 2500);
        }
    }, [csrfToken, purpose]);

    // Barcode gun mode — listen for rapid keystrokes ending with Enter
    useEffect(() => {
        if (mode !== 'barcode') return;

        let buffer = '';
        let timer: ReturnType<typeof setTimeout> | null = null;

        function onKeyDown(e: KeyboardEvent) {
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

            if (e.key.length === 1) {
                buffer += e.key;
                setBarcodeBuffer(buffer);

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
                        Pilih mode scanner dan tujuan scan.
                    </p>
                </div>

                {/* Purpose Toggle */}
                <div className="mx-auto flex w-full max-w-2xl gap-2">
                    <Button
                        variant={purpose === 'attendance' ? 'default' : 'outline'}
                        className="flex-1"
                        onClick={() => { setPurpose('attendance'); setValidateResult(null); }}
                    >
                        <UserCheck className="mr-2 size-4" />
                        Absensi
                    </Button>
                    <Button
                        variant={purpose === 'validate' ? 'default' : 'outline'}
                        className="flex-1"
                        onClick={() => { setPurpose('validate'); setValidateResult(null); }}
                    >
                        <ShieldCheck className="mr-2 size-4" />
                        Validasi Data
                    </Button>
                </div>

                {purpose === 'validate' && (
                    <div className="mx-auto w-full max-w-2xl rounded-lg border border-blue-200 bg-blue-50 p-3 text-center text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-200">
                        Mode Validasi: scan/input untuk mengecek data siswa tanpa mencatat absensi.
                    </div>
                )}

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
                        Kamera
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
                                <ResultBanner result={lastResult} />

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
                        <QrScanner scanEndpoint="/admin/scanner/scan" scanType="CHECK_IN" extraPayload={{ mode: purpose }} />
                    )}

                    {/* === MANUAL MODE === */}
                    {mode === 'manual' && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Keyboard className="size-4" />
                                    Input Manual
                                </CardTitle>
                                <CardDescription>
                                    Ketik NISN siswa untuk {purpose === 'attendance' ? 'absensi' : 'validasi data'}.
                                    NISN digunakan karena unik secara nasional (berbeda dengan NIS yang bisa sama antar sekolah).
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <form onSubmit={handleManualSubmit} className="flex gap-2">
                                    <Input
                                        type="text"
                                        placeholder="Ketik NISN siswa..."
                                        value={nis}
                                        onChange={(e) => setNis(e.target.value)}
                                        className="flex-1"
                                        autoFocus
                                    />
                                    <Button type="submit" disabled={!nis.trim()}>
                                        {purpose === 'attendance' ? 'Absen' : 'Validasi'}
                                    </Button>
                                </form>

                                <ResultBanner result={lastResult} />
                            </CardContent>
                        </Card>
                    )}

                    {/* === VALIDATE RESULT CARD === */}
                    {validateResult && (
                        <Card className="border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base text-green-800 dark:text-green-200">
                                    <CheckCircle2 className="size-5" />
                                    Data Siswa Valid
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex gap-4">
                                    {validateResult.photo_url ? (
                                        <img
                                            src={validateResult.photo_url}
                                            alt={validateResult.full_name}
                                            className="size-20 shrink-0 rounded-lg border-2 border-green-300 object-cover"
                                        />
                                    ) : (
                                        <div className="flex size-20 shrink-0 items-center justify-center rounded-lg border-2 border-green-300 bg-green-100 text-2xl dark:bg-green-900">
                                            👤
                                        </div>
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <h3 className="text-lg font-bold text-green-900 dark:text-green-100">{validateResult.full_name}</h3>
                                        <div className="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                            {validateResult.nisn && (
                                                <>
                                                    <span className="text-green-700 dark:text-green-300">NISN</span>
                                                    <span className="font-medium text-green-900 dark:text-green-100">{validateResult.nisn}</span>
                                                </>
                                            )}
                                            {validateResult.nis && (
                                                <>
                                                    <span className="text-green-700 dark:text-green-300">NIS</span>
                                                    <span className="font-medium text-green-900 dark:text-green-100">{validateResult.nis}</span>
                                                </>
                                            )}
                                            {validateResult.classroom && (
                                                <>
                                                    <span className="text-green-700 dark:text-green-300">Kelas</span>
                                                    <span className="font-medium text-green-900 dark:text-green-100">{validateResult.classroom}</span>
                                                </>
                                            )}
                                            {validateResult.no_absen && (
                                                <>
                                                    <span className="text-green-700 dark:text-green-300">No. Absen</span>
                                                    <span className="font-medium text-green-900 dark:text-green-100">{validateResult.no_absen}</span>
                                                </>
                                            )}
                                            {validateResult.gender && (
                                                <>
                                                    <span className="text-green-700 dark:text-green-300">Gender</span>
                                                    <span className="font-medium text-green-900 dark:text-green-100">{validateResult.gender}</span>
                                                </>
                                            )}
                                        </div>
                                        <div className="mt-3 flex gap-2">
                                            <Badge className="bg-green-200 text-green-800 dark:bg-green-800 dark:text-green-200">
                                                {validateResult.is_active ? 'Aktif' : 'Nonaktif'}
                                            </Badge>
                                            <Badge variant={validateResult.has_qr ? 'default' : 'secondary'}>
                                                {validateResult.has_qr ? 'QR Tersedia' : 'Belum Ada QR'}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                                <div className="mt-4 flex justify-end">
                                    <Button variant="outline" size="sm" onClick={() => setValidateResult(null)}>
                                        Tutup
                                    </Button>
                                </div>
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
                                                {entry.success ? (entry.mode === 'validate' ? 'VALID' : 'OK') : 'GAGAL'}
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

function ResultBanner({ result }: { result: { success: boolean; message: string; student?: any } | null }) {
    if (!result) return null;

    return (
        <div className={`rounded-xl p-4 text-center ${
            result.success
                ? 'border border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950'
                : 'border border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950'
        }`}>
            {result.success ? (
                <>
                    <p className="text-lg font-bold text-green-800 dark:text-green-200">{result.student?.full_name}</p>
                    <p className="text-sm text-green-600 dark:text-green-400">
                        {result.student?.classroom} &middot; {result.student?.status} &middot; {result.student?.time}
                    </p>
                </>
            ) : (
                <p className="font-semibold text-red-700 dark:text-red-300">{result.message}</p>
            )}
        </div>
    );
}

ScannerIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Scanner', href: '/admin/scanner' },
    ],
};
