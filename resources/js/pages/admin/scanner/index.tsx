import { Head } from '@inertiajs/react';
import { Check, CheckCircle2, LogIn, LogOut, ScanBarcode, User, X, XCircle, Zap } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { playErrorSound, playSuccessSound } from '@/components/scanner/use-scan-sound';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

type AttendanceType = 'CHECK_IN' | 'CHECK_OUT';

type StudentResult = {
    id: string;
    full_name: string;
    nis: string | null;
    classroom: string | null;
    photo_url: string | null;
    status?: string;
    type?: string;
    time?: string;
};

type ScanLogItem = {
    id: number;
    name: string;
    status: string;
    success: boolean;
    time: string;
    photo_url: string | null;
};

const COOLDOWN_MS = 2000;
let logId = 0;

export default function ScannerIndex() {
    const [attendanceType, setAttendanceType] = useState<AttendanceType>('CHECK_IN');
    const [lastStudent, setLastStudent] = useState<StudentResult | null>(null);
    const [lastError, setLastError] = useState<string | null>(null);
    const [scanLog, setScanLog] = useState<ScanLogItem[]>([]);
    const [cooldown, setCooldown] = useState(false);
    const [barcodeBuffer, setBarcodeBuffer] = useState('');
    const barcodeInputRef = useRef<HTMLInputElement>(null);
    const lastScannedRef = useRef<string>('');

    const csrfToken = typeof document !== 'undefined'
        ? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || ''
        : '';

    const submitScan = useCallback(async (token: string) => {
        if (!token.trim() || cooldown) return;

        // Prevent duplicate rapid scans of same token
        if (token === lastScannedRef.current) return;
        lastScannedRef.current = token;

        setCooldown(true);
        setLastError(null);

        try {
            const res = await fetch('/admin/scanner/scan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                body: JSON.stringify({ token: token.trim(), type: attendanceType, mode: 'attendance' }),
            });
            const data = await res.json();

            if (data.success && data.student) {
                setLastStudent(data.student);
                setLastError(null);
                playSuccessSound(data.student.full_name);

                setScanLog((prev) => [{
                    id: ++logId,
                    name: data.student.full_name,
                    status: `${data.student.type} - ${data.student.status} (${data.student.time})`,
                    success: true,
                    time: new Date().toLocaleTimeString('id-ID'),
                    photo_url: data.student.photo_url,
                }, ...prev].slice(0, 50));
            } else {
                setLastStudent(null);
                setLastError(data.message || 'Scan gagal.');
                playErrorSound(data.message);

                setScanLog((prev) => [{
                    id: ++logId,
                    name: token,
                    status: data.message || 'Gagal',
                    success: false,
                    time: new Date().toLocaleTimeString('id-ID'),
                    photo_url: null,
                }, ...prev].slice(0, 50));
            }
        } catch {
            setLastStudent(null);
            setLastError('Gagal menghubungi server.');
            playErrorSound('Gagal menghubungi server');
        }

        // Cooldown to prevent rapid duplicate scans
        setTimeout(() => {
            setCooldown(false);
            lastScannedRef.current = '';
        }, COOLDOWN_MS);
    }, [csrfToken, attendanceType, cooldown]);

    // Barcode gun — listen for rapid keystrokes ending with Enter
    useEffect(() => {
        let buffer = '';
        let timer: ReturnType<typeof setTimeout> | null = null;

        function onKeyDown(e: KeyboardEvent) {
            const tag = (e.target as HTMLElement)?.tagName;
            if (tag === 'TEXTAREA' || tag === 'SELECT') return;
            // Allow our barcode input
            if (tag === 'INPUT' && (e.target as HTMLInputElement) !== barcodeInputRef.current) return;

            if (e.key === 'Enter') {
                e.preventDefault();
                if (buffer.length >= 3) {
                    submitScan(buffer);
                }
                buffer = '';
                setBarcodeBuffer('');
                if (barcodeInputRef.current) barcodeInputRef.current.value = '';
                return;
            }

            if (e.key.length === 1) {
                buffer += e.key;
                setBarcodeBuffer(buffer);

                if (timer) clearTimeout(timer);
                timer = setTimeout(() => {
                    buffer = '';
                    setBarcodeBuffer('');
                }, 150);
            }
        }

        window.addEventListener('keydown', onKeyDown);
        return () => {
            window.removeEventListener('keydown', onKeyDown);
            if (timer) clearTimeout(timer);
        };
    }, [submitScan]);

    useEffect(() => {
        barcodeInputRef.current?.focus();
    }, []);

    function handleBarcodeInputSubmit(e: React.FormEvent) {
        e.preventDefault();
        const val = barcodeInputRef.current?.value?.trim();
        if (val && val.length >= 3) {
            submitScan(val);
        }
        if (barcodeInputRef.current) barcodeInputRef.current.value = '';
        setBarcodeBuffer('');
    }

    return (
        <>
            <Head title="Scanner Absensi" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Scanner Absensi</h1>
                    <p className="text-muted-foreground text-sm">Scan QR Code / Barcode siswa untuk mencatat kehadiran.</p>
                </div>

                {/* Attendance Type Toggle */}
                <div className="mx-auto flex w-full max-w-2xl gap-2">
                    <Button
                        variant={attendanceType === 'CHECK_IN' ? 'default' : 'outline'}
                        className="flex-1"
                        onClick={() => setAttendanceType('CHECK_IN')}
                    >
                        <LogIn className="mr-2 size-4" />
                        Masuk
                    </Button>
                    <Button
                        variant={attendanceType === 'CHECK_OUT' ? 'default' : 'outline'}
                        className="flex-1"
                        onClick={() => setAttendanceType('CHECK_OUT')}
                    >
                        <LogOut className="mr-2 size-4" />
                        Pulang
                    </Button>
                </div>

                <div className="mx-auto w-full max-w-2xl space-y-4">
                    {/* Barcode Scanner Card */}
                    <Card>
                        <CardHeader className="text-center">
                            <div className="bg-primary/10 text-primary mx-auto mb-2 flex size-16 items-center justify-center rounded-2xl">
                                <ScanBarcode className="size-8" />
                            </div>
                            <CardTitle>Barcode Scanner</CardTitle>
                            <CardDescription>
                                Arahkan barcode gun ke QR Code siswa. Hasil scan otomatis terdeteksi.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className={`rounded-xl border-2 border-dashed p-6 text-center transition-colors ${cooldown ? 'border-yellow-300 bg-yellow-50 dark:border-yellow-700 dark:bg-yellow-950' : 'border-primary/30 bg-primary/5'}`}>
                                <Zap className={`mx-auto mb-3 size-8 ${cooldown ? 'text-yellow-500' : 'text-primary'}`} />
                                <p className="text-sm font-semibold">{cooldown ? 'Memproses...' : 'Scanner Siap'}</p>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    Klik area ini lalu scan — data otomatis masuk via barcode gun.
                                </p>
                                {barcodeBuffer && (
                                    <div className="text-primary mt-3 font-mono text-lg font-bold tracking-wider">
                                        {barcodeBuffer}
                                        <span className="animate-pulse">|</span>
                                    </div>
                                )}

                                <form onSubmit={handleBarcodeInputSubmit} className="mt-3">
                                    <input
                                        ref={barcodeInputRef}
                                        type="text"
                                        className="border-input mx-auto block w-full max-w-xs rounded-lg border bg-white/50 px-3 py-2 text-center font-mono text-sm opacity-60 focus:opacity-100 focus:outline-none focus:ring-2 focus:ring-primary/30 dark:bg-black/20"
                                        placeholder="Klik sini lalu scan..."
                                        autoFocus
                                        autoComplete="off"
                                        disabled={cooldown}
                                    />
                                </form>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Student Result Card */}
                    {lastStudent && (
                        <Card className="border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950">
                            <CardContent className="pt-6">
                                <div className="flex items-center gap-2 mb-4">
                                    <CheckCircle2 className="size-5 text-green-600 dark:text-green-400" />
                                    <span className="font-semibold text-green-800 dark:text-green-200">
                                        {lastStudent.type} Berhasil
                                    </span>
                                </div>
                                <div className="flex gap-4">
                                    {lastStudent.photo_url ? (
                                        <img
                                            src={lastStudent.photo_url}
                                            alt={lastStudent.full_name}
                                            className="size-24 shrink-0 rounded-xl border-2 border-green-300 object-cover shadow-sm"
                                        />
                                    ) : (
                                        <div className="flex size-24 shrink-0 items-center justify-center rounded-xl border-2 border-green-300 bg-green-100 dark:bg-green-900">
                                            <User className="size-10 text-green-400" />
                                        </div>
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <h3 className="text-xl font-bold text-green-900 dark:text-green-100">
                                            {lastStudent.full_name}
                                        </h3>
                                        <div className="mt-2 space-y-1 text-sm">
                                            {lastStudent.nis && (
                                                <p className="text-green-700 dark:text-green-300">
                                                    <span className="font-medium">NIS:</span> {lastStudent.nis}
                                                </p>
                                            )}
                                            {lastStudent.classroom && (
                                                <p className="text-green-700 dark:text-green-300">
                                                    <span className="font-medium">Kelas:</span> {lastStudent.classroom}
                                                </p>
                                            )}
                                        </div>
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            <Badge className="bg-green-200 text-green-800 dark:bg-green-800 dark:text-green-200">
                                                {lastStudent.status}
                                            </Badge>
                                            <Badge variant="outline" className="border-green-300 text-green-700 dark:border-green-700 dark:text-green-300">
                                                {lastStudent.time}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Error Result */}
                    {lastError && (
                        <Card className="border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950">
                            <CardContent className="flex items-center gap-3 pt-6">
                                <XCircle className="size-6 shrink-0 text-red-500" />
                                <p className="font-semibold text-red-700 dark:text-red-300">{lastError}</p>
                            </CardContent>
                        </Card>
                    )}

                    {/* Scan Log */}
                    {scanLog.length > 0 && (
                        <Card>
                            <CardContent className="pt-6">
                                <h3 className="mb-3 text-sm font-semibold">Riwayat Scan ({scanLog.length})</h3>
                                <div className="max-h-72 space-y-2 overflow-y-auto">
                                    {scanLog.map((entry) => (
                                        <div
                                            key={entry.id}
                                            className={`flex items-center gap-3 rounded-lg border p-3 text-sm ${
                                                entry.success
                                                    ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950'
                                                    : 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950'
                                            }`}
                                        >
                                            {entry.photo_url ? (
                                                <img src={entry.photo_url} alt={entry.name} className="size-10 shrink-0 rounded-lg object-cover" />
                                            ) : (
                                                <div className={`flex size-10 shrink-0 items-center justify-center rounded-lg text-lg ${
                                                    entry.success ? 'bg-green-100 dark:bg-green-900' : 'bg-red-100 dark:bg-red-900'
                                                }`}>
                                                    {entry.success ? <Check className="size-5" /> : <X className="size-5" />}
                                                </div>
                                            )}
                                            <div className="min-w-0 flex-1">
                                                <p className="font-medium">{entry.name}</p>
                                                <p className="text-muted-foreground truncate text-xs">{entry.status}</p>
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
