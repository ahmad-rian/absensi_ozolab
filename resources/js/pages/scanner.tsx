import { Head, Link } from '@inertiajs/react';
import { Check, CheckCircle2, Clock, LogIn, LogOut, ScanBarcode, User, X, XCircle, Zap } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { playErrorSound, playSuccessSound } from '@/components/scanner/use-scan-sound';
import AppLogoIcon from '@/components/app-logo-icon';

type School = { id: string; name: string; slug: string; logo_url: string | null } | null;
type AttendanceType = 'CHECK_IN' | 'CHECK_OUT';

type StudentResult = {
    full_name: string;
    nis: string | null;
    nisn: string | null;
    no_absen: string | null;
    classroom: string | null;
    gender: string | null;
    photo_url: string | null;
    status: string;
    type: string;
    time: string;
};

type ScanResult = {
    success: boolean;
    message: string;
    student: StudentResult | null;
};

type ScanLogItem = {
    id: number;
    student: StudentResult | null;
    success: boolean;
    message: string;
    time: string;
};

let logId = 0;

export default function ScannerPage({ school, userName }: { school: School; userName: string }) {
    const [attendanceType, setAttendanceType] = useState<AttendanceType>('CHECK_IN');
    const [lastResult, setLastResult] = useState<ScanResult | null>(null);
    const [scanLog, setScanLog] = useState<ScanLogItem[]>([]);
    const [cooldown, setCooldown] = useState(false);
    const [barcodeBuffer, setBarcodeBuffer] = useState('');
    const barcodeInputRef = useRef<HTMLInputElement>(null);
    const lastTokenRef = useRef('');
    const resultTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);

    const csrfToken = typeof document !== 'undefined'
        ? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || ''
        : '';

    // Auto-detect time
    useEffect(() => {
        const hour = new Date().getHours();
        setAttendanceType(hour >= 12 ? 'CHECK_OUT' : 'CHECK_IN');
    }, []);

    const submitScan = useCallback(async (token: string) => {
        if (!token.trim() || cooldown) return;
        if (token === lastTokenRef.current) return;
        lastTokenRef.current = token;
        setCooldown(true);

        try {
            const res = await fetch('/scan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                body: JSON.stringify({ token: token.trim(), type: attendanceType }),
            });
            const data: ScanResult = await res.json();

            setLastResult(data);
            setScanLog((prev) => [{
                id: ++logId,
                student: data.student,
                success: data.success,
                message: data.message,
                time: new Date().toLocaleTimeString('id-ID'),
            }, ...prev].slice(0, 50));

            if (data.success) playSuccessSound(data.student?.full_name);
            else playErrorSound(data.message);

            if (resultTimeout.current) clearTimeout(resultTimeout.current);
            resultTimeout.current = setTimeout(() => setLastResult(null), 6000);
        } catch {
            playErrorSound('Gagal menghubungi server');
            setLastResult({ success: false, message: 'Gagal menghubungi server.', student: null });
        }

        setTimeout(() => { setCooldown(false); lastTokenRef.current = ''; }, 2000);
    }, [csrfToken, attendanceType, cooldown]);

    // Barcode gun listener
    useEffect(() => {
        let buffer = '';
        let timer: ReturnType<typeof setTimeout> | null = null;

        function onKeyDown(e: KeyboardEvent) {
            const tag = (e.target as HTMLElement)?.tagName;
            if (tag === 'TEXTAREA' || tag === 'SELECT') return;
            if (tag === 'INPUT' && (e.target as HTMLInputElement) !== barcodeInputRef.current) return;

            if (e.key === 'Enter') {
                e.preventDefault();
                if (buffer.length >= 3) submitScan(buffer);
                buffer = '';
                setBarcodeBuffer('');
                if (barcodeInputRef.current) barcodeInputRef.current.value = '';
                return;
            }
            if (e.key.length === 1) {
                buffer += e.key;
                setBarcodeBuffer(buffer);
                if (timer) clearTimeout(timer);
                timer = setTimeout(() => { buffer = ''; setBarcodeBuffer(''); }, 150);
            }
        }
        window.addEventListener('keydown', onKeyDown);
        return () => { window.removeEventListener('keydown', onKeyDown); if (timer) clearTimeout(timer); };
    }, [submitScan]);

    useEffect(() => { barcodeInputRef.current?.focus(); }, []);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        const val = barcodeInputRef.current?.value?.trim();
        if (val && val.length >= 3) submitScan(val);
        if (barcodeInputRef.current) barcodeInputRef.current.value = '';
        setBarcodeBuffer('');
    }

    const isCheckIn = attendanceType === 'CHECK_IN';

    return (
        <>
            <Head title="Scan Absensi" />
            <div className="flex min-h-dvh flex-col bg-gradient-to-br from-slate-50 via-blue-50/30 to-slate-100 dark:from-zinc-950 dark:via-zinc-900 dark:to-zinc-950">
                {/* Header */}
                <header className="sticky top-0 z-50 border-b bg-white/90 backdrop-blur-xl dark:bg-zinc-900/90">
                    <div className="mx-auto flex h-16 max-w-3xl items-center justify-between px-5">
                        <div className="flex items-center gap-3">
                            {school?.logo_url ? (
                                <img src={school.logo_url} alt={school.name} className="size-10 rounded-xl object-contain shadow-sm" />
                            ) : (
                                <div className="flex size-10 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 shadow-sm">
                                    <AppLogoIcon className="size-5 fill-current text-white" />
                                </div>
                            )}
                            <div>
                                <p className="text-base font-bold leading-tight">{school?.name ?? 'Absensi'}</p>
                                <p className="text-muted-foreground text-xs">{userName}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
                            {/* Attendance toggle */}
                            <div className="flex rounded-full bg-slate-100 p-1 dark:bg-zinc-800">
                                <button
                                    onClick={() => setAttendanceType('CHECK_IN')}
                                    className={`flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold transition-all ${
                                        isCheckIn ? 'bg-emerald-500 text-white shadow-md shadow-emerald-200' : 'text-slate-500 hover:text-slate-700'
                                    }`}
                                >
                                    <LogIn className="size-4" /> Masuk
                                </button>
                                <button
                                    onClick={() => setAttendanceType('CHECK_OUT')}
                                    className={`flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold transition-all ${
                                        !isCheckIn ? 'bg-orange-500 text-white shadow-md shadow-orange-200' : 'text-slate-500 hover:text-slate-700'
                                    }`}
                                >
                                    <LogOut className="size-4" /> Pulang
                                </button>
                            </div>
                            <Link href="/admin/dashboard" className="text-muted-foreground hover:text-foreground text-sm underline underline-offset-2">
                                Dashboard
                            </Link>
                        </div>
                    </div>
                </header>

                {/* Main Content */}
                <main className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-5 px-5 py-6">
                    {/* Scanner Card */}
                    <div className={`rounded-2xl border-2 border-dashed p-8 text-center transition-all ${
                        cooldown
                            ? 'border-amber-300 bg-amber-50/50 dark:border-amber-700 dark:bg-amber-950/30'
                            : 'border-blue-200 bg-white dark:border-blue-800 dark:bg-zinc-800/50'
                    }`}>
                        <div className={`mx-auto mb-4 flex size-16 items-center justify-center rounded-2xl ${
                            cooldown ? 'bg-amber-100 text-amber-600' : 'bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-400'
                        }`}>
                            {cooldown ? <Clock className="size-8 animate-spin" /> : <ScanBarcode className="size-8" />}
                        </div>
                        <h2 className="text-xl font-bold">{cooldown ? 'Memproses...' : 'Scanner Siap'}</h2>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Arahkan barcode gun ke QR Code siswa atau klik input di bawah.
                        </p>
                        {barcodeBuffer && (
                            <div className="mt-4 font-mono text-2xl font-bold tracking-widest text-blue-600">
                                {barcodeBuffer}<span className="animate-pulse">|</span>
                            </div>
                        )}
                        <form onSubmit={handleSubmit} className="mt-5">
                            <input
                                ref={barcodeInputRef}
                                type="text"
                                className="mx-auto block w-full max-w-sm rounded-xl border-2 border-slate-200 bg-slate-50 px-4 py-3 text-center font-mono text-lg tracking-wide transition-all focus:border-blue-400 focus:bg-white focus:outline-none focus:ring-4 focus:ring-blue-100 dark:border-zinc-600 dark:bg-zinc-800 dark:focus:border-blue-500 dark:focus:ring-blue-900"
                                placeholder="Klik sini lalu scan..."
                                autoFocus
                                autoComplete="off"
                                disabled={cooldown}
                            />
                        </form>
                    </div>

                    {/* Result Card */}
                    {lastResult && (
                        <div className={`overflow-hidden rounded-2xl border-2 shadow-xl transition-all ${
                            lastResult.success
                                ? 'border-emerald-200 bg-white dark:border-emerald-800 dark:bg-zinc-900'
                                : 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950/50'
                        }`}>
                            {lastResult.success && lastResult.student ? (
                                <>
                                    <div className="flex gap-5 p-6">
                                        <div className="shrink-0">
                                            {lastResult.student.photo_url ? (
                                                <img
                                                    src={lastResult.student.photo_url}
                                                    alt={lastResult.student.full_name}
                                                    className="size-28 rounded-2xl border-3 border-emerald-300 object-cover shadow-lg"
                                                />
                                            ) : (
                                                <div className="flex size-28 items-center justify-center rounded-2xl border-3 border-emerald-300 bg-gradient-to-br from-emerald-50 to-green-100 shadow-lg dark:from-emerald-900 dark:to-green-900">
                                                    <User className="size-12 text-emerald-400" />
                                                </div>
                                            )}
                                        </div>
                                        <div className="min-w-0 flex-1 py-1">
                                            <h3 className="text-2xl font-bold text-emerald-800 dark:text-emerald-200">
                                                {lastResult.student.full_name}
                                            </h3>
                                            <div className="mt-2 space-y-1 text-base">
                                                {lastResult.student.classroom && (
                                                    <p className="text-emerald-700 dark:text-emerald-300">
                                                        <span className="font-medium">Kelas</span> {lastResult.student.classroom}
                                                    </p>
                                                )}
                                                {lastResult.student.nis && (
                                                    <p className="text-emerald-600 dark:text-emerald-400">
                                                        NIS: {lastResult.student.nis}
                                                    </p>
                                                )}
                                                {lastResult.student.no_absen && (
                                                    <p className="text-emerald-600 dark:text-emerald-400">
                                                        No. Absen: {lastResult.student.no_absen}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex items-center justify-between bg-emerald-50 px-6 py-4 dark:bg-emerald-950/50">
                                        <div className="flex items-center gap-2.5">
                                            <CheckCircle2 className="size-6 text-emerald-600" />
                                            <span className="text-lg font-bold text-emerald-800 dark:text-emerald-200">
                                                {lastResult.student.status}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2 text-base font-medium text-emerald-600 dark:text-emerald-400">
                                            <Clock className="size-4" />
                                            {lastResult.student.time}
                                        </div>
                                    </div>
                                </>
                            ) : (
                                <div className="flex items-center gap-4 p-6">
                                    <XCircle className="size-12 shrink-0 text-red-500" />
                                    <p className="text-lg font-bold text-red-700 dark:text-red-300">{lastResult.message}</p>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Scan Log */}
                    {scanLog.length > 0 && (
                        <div className="rounded-2xl border bg-white shadow-sm dark:bg-zinc-800/50">
                            <div className="border-b px-5 py-3.5">
                                <h3 className="text-sm font-bold">Riwayat Hari Ini ({scanLog.length})</h3>
                            </div>
                            <div className="max-h-72 divide-y overflow-y-auto">
                                {scanLog.map((entry) => (
                                    <div key={entry.id} className="flex items-center gap-3.5 px-5 py-3">
                                        {entry.success && entry.student?.photo_url ? (
                                            <img src={entry.student.photo_url} alt="" className="size-11 shrink-0 rounded-xl object-cover shadow-sm" />
                                        ) : (
                                            <div className={`flex size-11 shrink-0 items-center justify-center rounded-xl text-sm font-bold text-white ${
                                                entry.success ? 'bg-emerald-500' : 'bg-red-500'
                                            }`}>
                                                {entry.success ? <Check className="size-5" /> : <X className="size-5" />}
                                            </div>
                                        )}
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-semibold">{entry.student?.full_name || entry.message}</p>
                                            <p className="text-muted-foreground text-xs">
                                                {entry.student?.classroom} {entry.student?.status && `· ${entry.student.status}`}
                                            </p>
                                        </div>
                                        <span className="text-muted-foreground shrink-0 text-xs font-medium">{entry.time}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </main>
            </div>
        </>
    );
}
