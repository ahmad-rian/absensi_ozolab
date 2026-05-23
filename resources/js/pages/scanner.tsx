import { Head, Link } from '@inertiajs/react';
import { Camera, CheckCircle2, Clock, Keyboard, LogIn, LogOut, QrCode, XCircle } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { QrScanner } from '@/components/scanner/qr-scanner';
import { playErrorSound, playSuccessSound } from '@/components/scanner/use-scan-sound';
import AppLogoIcon from '@/components/app-logo-icon';

type School = { id: number; name: string; slug: string };

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

export default function PublicScanner({ schools }: { schools: School[] }) {
    const [selectedSchoolId, setSelectedSchoolId] = useState<number | null>(
        schools.length === 1 ? schools[0].id : null,
    );
    const [mode, setMode] = useState<'camera' | 'manual'>('camera');
    const [attendanceType, setAttendanceType] = useState<'CHECK_IN' | 'CHECK_OUT'>('CHECK_IN');
    const [manualInput, setManualInput] = useState('');
    const [lastResult, setLastResult] = useState<ScanResult | null>(null);
    const [scanLog, setScanLog] = useState<ScanLogItem[]>([]);
    const resultTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);

    const selectedSchool = schools.find((s) => s.id === selectedSchoolId);

    const csrfToken = typeof document !== 'undefined'
        ? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || ''
        : '';

    const submitManualScan = useCallback(async (token: string) => {
        if (!token.trim() || !selectedSchoolId) return;
        try {
            const res = await fetch('/scan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                body: JSON.stringify({ token: token.trim(), school_id: selectedSchoolId, type: attendanceType }),
            });
            const data: ScanResult = await res.json();

            setLastResult(data);
            setScanLog((prev) => [{
                id: ++logId,
                student: data.student,
                success: data.success,
                message: data.message,
                time: new Date().toLocaleTimeString('id-ID'),
            }, ...prev].slice(0, 30));

            if (data.success) playSuccessSound(data.student?.full_name);
            else playErrorSound(data.message);

            if (resultTimeout.current) clearTimeout(resultTimeout.current);
            resultTimeout.current = setTimeout(() => setLastResult(null), 5000);
        } catch {
            playErrorSound('Gagal menghubungi server');
            setLastResult({ success: false, message: 'Gagal menghubungi server.', student: null });
        }
    }, [csrfToken, selectedSchoolId, attendanceType]);

    function handleManualSubmit(e: React.FormEvent) {
        e.preventDefault();
        submitManualScan(manualInput);
        setManualInput('');
    }

    // Auto-detect time for default type
    useEffect(() => {
        const hour = new Date().getHours();
        setAttendanceType(hour >= 12 ? 'CHECK_OUT' : 'CHECK_IN');
    }, []);

    return (
        <>
            <Head title="Scanner Absensi" />
            <div className="min-h-dvh bg-gradient-to-b from-slate-50 to-slate-100 dark:from-zinc-950 dark:to-zinc-900">
                {/* Header */}
                <header className="border-b bg-white/80 backdrop-blur-sm dark:bg-zinc-900/80">
                    <div className="mx-auto flex h-14 max-w-xl items-center justify-between px-4">
                        <Link href="/" className="flex items-center gap-2">
                            <div className="flex size-8 items-center justify-center rounded-lg bg-gradient-to-br from-blue-600 to-indigo-600">
                                <AppLogoIcon className="size-4 fill-current text-white" />
                            </div>
                            <span className="text-sm font-bold">{selectedSchool?.name ?? 'Absensi OZOLAB'}</span>
                        </Link>
                        <div className="flex items-center gap-1 rounded-full bg-slate-100 p-0.5 dark:bg-zinc-800">
                            <button
                                onClick={() => setAttendanceType('CHECK_IN')}
                                className={`flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium transition ${
                                    attendanceType === 'CHECK_IN'
                                        ? 'bg-green-500 text-white shadow-sm'
                                        : 'text-slate-500 hover:text-slate-700'
                                }`}
                            >
                                <LogIn className="size-3" /> Masuk
                            </button>
                            <button
                                onClick={() => setAttendanceType('CHECK_OUT')}
                                className={`flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium transition ${
                                    attendanceType === 'CHECK_OUT'
                                        ? 'bg-orange-500 text-white shadow-sm'
                                        : 'text-slate-500 hover:text-slate-700'
                                }`}
                            >
                                <LogOut className="size-3" /> Pulang
                            </button>
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-xl px-4 py-4">
                    {/* School selector */}
                    {!selectedSchoolId ? (
                        <div className="mt-8 space-y-4 text-center">
                            <QrCode className="mx-auto size-12 text-blue-500" />
                            <h1 className="text-xl font-bold">Scanner Absensi</h1>
                            <p className="text-muted-foreground text-sm">Pilih sekolah:</p>
                            <div className="grid gap-2">
                                {schools.map((school) => (
                                    <button
                                        key={school.id}
                                        type="button"
                                        onClick={() => setSelectedSchoolId(school.id)}
                                        className="rounded-xl border bg-white px-4 py-3 text-left text-sm font-medium shadow-sm transition hover:bg-blue-50 hover:border-blue-200 dark:bg-zinc-800 dark:hover:bg-zinc-700"
                                    >
                                        {school.name}
                                    </button>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {/* School switcher */}
                            {schools.length > 1 && (
                                <select
                                    value={selectedSchoolId}
                                    onChange={(e) => setSelectedSchoolId(Number(e.target.value))}
                                    className="w-full rounded-lg border bg-white px-3 py-2 text-sm dark:bg-zinc-800"
                                >
                                    {schools.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                </select>
                            )}

                            {/* Mode toggle */}
                            <div className="flex gap-2">
                                <button
                                    onClick={() => setMode('camera')}
                                    className={`flex flex-1 items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-medium transition ${
                                        mode === 'camera'
                                            ? 'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300'
                                            : 'bg-white hover:bg-slate-50 dark:bg-zinc-800'
                                    }`}
                                >
                                    <Camera className="size-4" /> Kamera
                                </button>
                                <button
                                    onClick={() => setMode('manual')}
                                    className={`flex flex-1 items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-medium transition ${
                                        mode === 'manual'
                                            ? 'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300'
                                            : 'bg-white hover:bg-slate-50 dark:bg-zinc-800'
                                    }`}
                                >
                                    <Keyboard className="size-4" /> NISN
                                </button>
                            </div>

                            {/* Camera scanner */}
                            {mode === 'camera' && (
                                <QrScanner
                                    scanEndpoint="/scan"
                                    scanType={attendanceType}
                                    extraPayload={{ school_id: selectedSchoolId }}
                                />
                            )}

                            {/* Manual input */}
                            {mode === 'manual' && (
                                <div className="rounded-xl border bg-white p-4 shadow-sm dark:bg-zinc-800">
                                    <form onSubmit={handleManualSubmit} className="flex gap-2">
                                        <input
                                            type="text"
                                            value={manualInput}
                                            onChange={(e) => setManualInput(e.target.value)}
                                            placeholder="Ketik NISN siswa..."
                                            className="flex-1 rounded-lg border px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                                            autoFocus
                                        />
                                        <button
                                            type="submit"
                                            disabled={!manualInput.trim()}
                                            className="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-50"
                                        >
                                            Absen
                                        </button>
                                    </form>
                                </div>
                            )}

                            {/* Result Card — shows after scan */}
                            {lastResult && (
                                <div className={`overflow-hidden rounded-2xl border shadow-lg ${
                                    lastResult.success
                                        ? 'border-green-200 bg-white dark:border-green-800 dark:bg-zinc-900'
                                        : 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950'
                                }`}>
                                    {lastResult.success && lastResult.student ? (
                                        <div>
                                            {/* Student photo + info */}
                                            <div className="flex gap-4 p-4">
                                                <div className="shrink-0">
                                                    {lastResult.student.photo_url ? (
                                                        <img
                                                            src={lastResult.student.photo_url}
                                                            alt={lastResult.student.full_name}
                                                            className="size-24 rounded-xl border-2 border-green-300 object-cover shadow-md"
                                                        />
                                                    ) : (
                                                        <div className="flex size-24 items-center justify-center rounded-xl border-2 border-green-300 bg-gradient-to-br from-green-100 to-emerald-100 text-3xl shadow-md dark:from-green-900 dark:to-emerald-900">
                                                            👤
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <h3 className="text-lg font-bold text-green-800 dark:text-green-200">
                                                        {lastResult.student.full_name}
                                                    </h3>
                                                    <div className="mt-1 space-y-0.5 text-sm text-green-700 dark:text-green-300">
                                                        {lastResult.student.classroom && (
                                                            <p>Kelas {lastResult.student.classroom}</p>
                                                        )}
                                                        {lastResult.student.nis && (
                                                            <p>NIS: {lastResult.student.nis}</p>
                                                        )}
                                                        {lastResult.student.no_absen && (
                                                            <p>No. Absen: {lastResult.student.no_absen}</p>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                            {/* Status bar */}
                                            <div className="flex items-center justify-between bg-green-50 px-4 py-3 dark:bg-green-950">
                                                <div className="flex items-center gap-2">
                                                    <CheckCircle2 className="size-5 text-green-600" />
                                                    <span className="text-sm font-semibold text-green-800 dark:text-green-200">
                                                        {lastResult.student.status}
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-1.5 text-sm text-green-600 dark:text-green-400">
                                                    <Clock className="size-3.5" />
                                                    {lastResult.student.time}
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="flex items-center gap-3 p-4">
                                            <XCircle className="size-10 shrink-0 text-red-500" />
                                            <p className="font-semibold text-red-700 dark:text-red-300">{lastResult.message}</p>
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* Scan Log */}
                            {scanLog.length > 0 && (
                                <div className="rounded-xl border bg-white shadow-sm dark:bg-zinc-800">
                                    <div className="border-b px-4 py-3">
                                        <h3 className="text-sm font-semibold">Riwayat Hari Ini ({scanLog.length})</h3>
                                    </div>
                                    <div className="max-h-60 divide-y overflow-y-auto">
                                        {scanLog.map((entry) => (
                                            <div key={entry.id} className="flex items-center gap-3 px-4 py-2.5">
                                                {entry.success && entry.student?.photo_url ? (
                                                    <img src={entry.student.photo_url} alt="" className="size-9 shrink-0 rounded-lg object-cover" />
                                                ) : (
                                                    <div className={`flex size-9 shrink-0 items-center justify-center rounded-lg text-xs font-bold text-white ${
                                                        entry.success ? 'bg-green-500' : 'bg-red-500'
                                                    }`}>
                                                        {entry.success ? '✓' : '✗'}
                                                    </div>
                                                )}
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-medium">
                                                        {entry.student?.full_name || entry.message}
                                                    </p>
                                                    <p className="text-muted-foreground text-xs">
                                                        {entry.student?.classroom} {entry.student?.status && `· ${entry.student.status}`}
                                                    </p>
                                                </div>
                                                <span className="text-muted-foreground shrink-0 text-xs">{entry.time}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </main>
            </div>
        </>
    );
}
