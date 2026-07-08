import { Head } from '@inertiajs/react';
import { Html5Qrcode } from 'html5-qrcode';
import {
    Camera,
    CheckCircle2,
    Clock,
    Loader2,
    LogIn,
    LogOut,
    RefreshCw,
    ScanLine,
    School as SchoolIcon,
    User,
    XCircle,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { playErrorSound, playSuccessSound } from '@/components/scanner/use-scan-sound';

type School = { name: string; logo_url: string | null; is_active: boolean };

type StudentResult = {
    full_name: string;
    nis: string | null;
    nisn: string | null;
    no_absen: string | null;
    classroom: string | null;
    gender: string | null;
    photo_url: string | null;
    status: string;
    type: 'CHECK_IN' | 'CHECK_OUT';
    type_label: string;
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

type CameraDevice = { id: string; label: string };

type PageProps = {
    school: School;
    scanToken: string;
};

let logId = 0;

export default function PublicScanPage({ school, scanToken }: PageProps) {
    const [cameraStatus, setCameraStatus] = useState<'loading' | 'scanning' | 'error'>('loading');
    const [cameraError, setCameraError] = useState<string | null>(null);
    const [cameras, setCameras] = useState<CameraDevice[]>([]);
    const [selectedCamera, setSelectedCamera] = useState<string>('');
    const [lastResult, setLastResult] = useState<ScanResult | null>(null);
    const [scanLog, setScanLog] = useState<ScanLogItem[]>([]);
    const [cooldown, setCooldown] = useState(false);
    const [clock, setClock] = useState('');

    const barcodeInputRef = useRef<HTMLInputElement>(null);
    const scannerRef = useRef<Html5Qrcode | null>(null);
    const mountedRef = useRef(true);
    const cooldownRef = useRef(false);
    const lastTokenRef = useRef('');
    const resultTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);
    const readerId = 'public-qr-reader';

    const csrfToken =
        typeof document !== 'undefined'
            ? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || ''
            : '';

    // Live clock
    useEffect(() => {
        const tick = () => setClock(new Date().toLocaleTimeString('id-ID'));
        tick();
        const id = setInterval(tick, 1000);
        return () => clearInterval(id);
    }, []);

    const submitScan = useCallback(
        async (rawToken: string) => {
            const token = rawToken.trim();
            if (!token || token.length < 3) return;
            if (cooldownRef.current) return;
            if (token === lastTokenRef.current) return;

            cooldownRef.current = true;
            lastTokenRef.current = token;
            setCooldown(true);

            try {
                const res = await fetch(`/scan/${scanToken}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ token }),
                });
                const data: ScanResult = await res.json();
                if (!mountedRef.current) return;

                setLastResult(data);
                setScanLog((prev) =>
                    [
                        {
                            id: ++logId,
                            student: data.student,
                            success: data.success,
                            message: data.message,
                            time: new Date().toLocaleTimeString('id-ID'),
                        },
                        ...prev,
                    ].slice(0, 50),
                );

                if (data.success) playSuccessSound(data.student?.full_name);
                else playErrorSound(data.message);
            } catch {
                if (!mountedRef.current) return;
                setLastResult({ success: false, message: 'Gagal menghubungi server.', student: null });
                playErrorSound('Gagal menghubungi server');
            }

            if (resultTimeout.current) clearTimeout(resultTimeout.current);
            resultTimeout.current = setTimeout(() => {
                if (mountedRef.current) setLastResult(null);
            }, 5000);

            setTimeout(() => {
                cooldownRef.current = false;
                lastTokenRef.current = '';
                if (mountedRef.current) setCooldown(false);
            }, 1800);
        },
        [csrfToken, scanToken],
    );

    // ---- Camera (live QR) ----
    const stopScanner = useCallback(async () => {
        if (scannerRef.current) {
            try {
                if (scannerRef.current.isScanning) await scannerRef.current.stop();
            } catch {
                /* noop */
            }
            try {
                scannerRef.current.clear();
            } catch {
                /* noop */
            }
            scannerRef.current = null;
        }
    }, []);

    const startWithCamera = useCallback(
        async (cameraId: string) => {
            setCameraStatus('loading');
            setCameraError(null);
            await stopScanner();

            const el = document.getElementById(readerId);
            if (!el) {
                setCameraError('Elemen scanner tidak ditemukan.');
                setCameraStatus('error');
                return;
            }
            el.innerHTML = '';

            try {
                const scanner = new Html5Qrcode(readerId);
                scannerRef.current = scanner;

                await scanner.start(
                    cameraId ? { deviceId: { exact: cameraId } } : { facingMode: 'environment' },
                    {
                        fps: 15,
                        qrbox: { width: 260, height: 260 },
                        aspectRatio: 1,
                        disableFlip: false,
                        experimentalFeatures: { useBarCodeDetectorIfSupported: true },
                    },
                    (text) => submitScan(text),
                    () => {},
                );
                if (mountedRef.current) setCameraStatus('scanning');
            } catch (err) {
                if (!mountedRef.current) return;
                const msg = err instanceof Error ? err.message : String(err);
                if (msg.includes('Permission') || msg.includes('NotAllowed')) {
                    setCameraError('Akses kamera ditolak. Izinkan kamera di pengaturan browser.');
                } else if (msg.includes('NotFound') || msg.includes('not found')) {
                    setCameraError('Kamera tidak ditemukan. Gunakan barcode gun / input manual.');
                } else {
                    setCameraError(`Gagal memulai kamera: ${msg}`);
                }
                setCameraStatus('error');
            }
        },
        [stopScanner, submitScan],
    );

    // Init cameras + auto-start
    useEffect(() => {
        mountedRef.current = true;

        async function init() {
            await new Promise((r) => setTimeout(r, 200));
            if (!mountedRef.current) return;
            try {
                const devices = await Html5Qrcode.getCameras();
                if (!mountedRef.current) return;

                const cams = devices.map((d) => ({ id: d.id, label: d.label || `Kamera ${d.id.slice(0, 6)}` }));
                setCameras(cams);

                const external = cams.find((c) => /usb|iware|external|back|rear|hd|web/i.test(c.label));
                const pick = external || cams[cams.length - 1] || cams[0];

                if (pick) {
                    setSelectedCamera(pick.id);
                    await startWithCamera(pick.id);
                } else {
                    await startWithCamera('');
                }
            } catch (err) {
                if (!mountedRef.current) return;
                setCameraError('Tidak bisa mengakses kamera: ' + (err instanceof Error ? err.message : ''));
                setCameraStatus('error');
            }
        }

        init();

        return () => {
            mountedRef.current = false;
            stopScanner();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // ---- Barcode gun (global keyboard listener) ----
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
                if (barcodeInputRef.current) barcodeInputRef.current.value = '';
                return;
            }
            if (e.key.length === 1) {
                buffer += e.key;
                if (timer) clearTimeout(timer);
                timer = setTimeout(() => {
                    buffer = '';
                }, 150);
            }
        }

        window.addEventListener('keydown', onKeyDown);
        return () => {
            window.removeEventListener('keydown', onKeyDown);
            if (timer) clearTimeout(timer);
        };
    }, [submitScan]);

    function handleManualSubmit(e: React.FormEvent) {
        e.preventDefault();
        const val = barcodeInputRef.current?.value?.trim();
        if (val && val.length >= 3) submitScan(val);
        if (barcodeInputRef.current) barcodeInputRef.current.value = '';
    }

    // ---- Inactive school ----
    if (!school.is_active) {
        return (
            <>
                <Head title={`Absensi — ${school.name}`} />
                <div className="flex min-h-dvh flex-col items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 px-6 text-center">
                    <div className="flex size-16 items-center justify-center rounded-2xl bg-white/10 text-white">
                        <SchoolIcon className="size-8" />
                    </div>
                    <h1 className="mt-5 text-2xl font-bold text-white">{school.name}</h1>
                    <p className="mt-2 max-w-md text-slate-300">Halaman absensi sekolah ini sedang tidak aktif.</p>
                </div>
            </>
        );
    }

    const success = lastResult?.success && lastResult.student;
    const isCheckIn = lastResult?.student?.type === 'CHECK_IN';

    return (
        <>
            <Head title={`Scan Absensi — ${school.name}`} />
            <div className="flex min-h-dvh flex-col bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 text-white">
                {/* Header */}
                <header className="sticky top-0 z-40 border-b border-white/10 bg-slate-950/60 backdrop-blur-xl">
                    <div className="mx-auto flex h-16 w-full max-w-3xl items-center justify-between px-5">
                        <div className="flex items-center gap-3">
                            {school.logo_url ? (
                                <img src={school.logo_url} alt={school.name} className="size-10 rounded-xl bg-white/90 object-contain p-0.5" />
                            ) : (
                                <div className="flex size-10 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600">
                                    <SchoolIcon className="size-5" />
                                </div>
                            )}
                            <div className="leading-tight">
                                <p className="text-sm font-bold">{school.name}</p>
                                <p className="text-xs text-slate-400">Absensi Digital</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2 rounded-full bg-white/5 px-3.5 py-1.5 font-mono text-sm font-semibold tabular-nums text-slate-200">
                            <Clock className="size-4 text-slate-400" />
                            {clock}
                        </div>
                    </div>
                </header>

                <main className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-5 px-5 py-6">
                    {/* Camera hero */}
                    <div className="relative overflow-hidden rounded-3xl border border-white/10 bg-slate-950 shadow-2xl">
                        <div className="relative aspect-square w-full sm:aspect-[4/3]">
                            <div id={readerId} className="size-full [&>video]:!size-full [&>video]:!object-cover" />

                            {/* Scan frame overlay */}
                            {cameraStatus === 'scanning' && !lastResult && (
                                <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
                                    <div className="relative size-56 sm:size-64">
                                        <span className="absolute left-0 top-0 size-8 rounded-tl-xl border-l-4 border-t-4 border-emerald-400" />
                                        <span className="absolute right-0 top-0 size-8 rounded-tr-xl border-r-4 border-t-4 border-emerald-400" />
                                        <span className="absolute bottom-0 left-0 size-8 rounded-bl-xl border-b-4 border-l-4 border-emerald-400" />
                                        <span className="absolute bottom-0 right-0 size-8 rounded-br-xl border-b-4 border-r-4 border-emerald-400" />
                                        <ScanLine className="absolute inset-x-0 top-1/2 mx-auto size-10 -translate-y-1/2 animate-pulse text-emerald-400/80" />
                                    </div>
                                </div>
                            )}

                            {cameraStatus === 'scanning' && !lastResult && (
                                <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-950/90 to-transparent p-4 text-center">
                                    <p className="text-sm font-medium text-slate-200">Arahkan QR Code siswa ke kamera</p>
                                    <p className="mt-0.5 text-xs text-slate-400">atau tembak dengan barcode gun — otomatis terdeteksi</p>
                                </div>
                            )}

                            {cameraStatus === 'loading' && (
                                <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-slate-950">
                                    <Loader2 className="size-10 animate-spin text-slate-500" />
                                    <p className="text-sm text-slate-400">Membuka kamera...</p>
                                </div>
                            )}

                            {cameraStatus === 'error' && (
                                <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-slate-950 px-6 text-center">
                                    <Camera className="size-10 text-slate-500" />
                                    <p className="text-sm text-slate-300">{cameraError}</p>
                                    <button
                                        onClick={() => startWithCamera(selectedCamera)}
                                        className="mt-1 inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-4 py-2 text-sm font-medium hover:bg-white/20"
                                    >
                                        <RefreshCw className="size-4" /> Coba Lagi
                                    </button>
                                    <p className="mt-1 text-xs text-slate-500">Barcode gun tetap berfungsi tanpa kamera.</p>
                                </div>
                            )}

                            {/* Result overlay */}
                            {lastResult && (
                                <div className="absolute inset-0 z-10 flex items-center justify-center bg-slate-950/85 p-5 backdrop-blur-sm">
                                    {success && lastResult.student ? (
                                        <div className="w-full max-w-sm rounded-2xl bg-white p-5 text-slate-900 shadow-2xl">
                                            <div className="flex gap-4">
                                                {lastResult.student.photo_url ? (
                                                    <img
                                                        src={lastResult.student.photo_url}
                                                        alt={lastResult.student.full_name}
                                                        className="size-24 shrink-0 rounded-2xl border-4 border-emerald-300 object-cover shadow"
                                                    />
                                                ) : (
                                                    <div className="flex size-24 shrink-0 items-center justify-center rounded-2xl border-4 border-emerald-300 bg-emerald-50">
                                                        <User className="size-10 text-emerald-400" />
                                                    </div>
                                                )}
                                                <div className="min-w-0 flex-1">
                                                    <span
                                                        className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold text-white ${
                                                            isCheckIn ? 'bg-emerald-500' : 'bg-orange-500'
                                                        }`}
                                                    >
                                                        {isCheckIn ? <LogIn className="size-3" /> : <LogOut className="size-3" />}
                                                        {lastResult.student.type_label}
                                                    </span>
                                                    <h3 className="mt-1.5 truncate text-lg font-bold">{lastResult.student.full_name}</h3>
                                                    <p className="truncate text-sm text-slate-500">
                                                        {lastResult.student.classroom}
                                                        {lastResult.student.nis ? ` · ${lastResult.student.nis}` : ''}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="mt-4 flex items-center justify-between rounded-xl bg-emerald-50 px-4 py-2.5">
                                                <span className="flex items-center gap-1.5 font-bold text-emerald-700">
                                                    <CheckCircle2 className="size-5" /> {lastResult.student.status}
                                                </span>
                                                <span className="flex items-center gap-1.5 font-mono text-sm font-semibold text-emerald-600">
                                                    <Clock className="size-4" /> {lastResult.student.time}
                                                </span>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
                                            <XCircle className="mx-auto size-12 text-red-500" />
                                            <p className="mt-3 text-lg font-bold text-red-600">{lastResult.message}</p>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Camera selector */}
                        {cameras.length > 1 && (
                            <div className="flex items-center gap-2 border-t border-white/10 px-4 py-3">
                                <Camera className="size-4 shrink-0 text-slate-400" />
                                <select
                                    value={selectedCamera}
                                    onChange={(e) => {
                                        setSelectedCamera(e.target.value);
                                        startWithCamera(e.target.value);
                                    }}
                                    className="flex-1 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-200"
                                >
                                    {cameras.map((cam) => (
                                        <option key={cam.id} value={cam.id} className="bg-slate-800">
                                            {cam.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}
                    </div>

                    {/* Manual / gun input */}
                    <form onSubmit={handleManualSubmit} className="flex gap-2">
                        <input
                            ref={barcodeInputRef}
                            type="text"
                            inputMode="text"
                            autoComplete="off"
                            autoFocus
                            disabled={cooldown}
                            placeholder="Tembak barcode gun atau ketik NIS lalu Enter..."
                            className="flex-1 rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-center font-mono tracking-wide text-white placeholder:text-slate-500 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/30 disabled:opacity-50"
                        />
                    </form>

                    {/* Recent log */}
                    {scanLog.length > 0 && (
                        <div className="overflow-hidden rounded-2xl border border-white/10 bg-white/5">
                            <div className="border-b border-white/10 px-5 py-3">
                                <h3 className="text-sm font-bold text-slate-200">Riwayat Scan ({scanLog.length})</h3>
                            </div>
                            <div className="max-h-64 divide-y divide-white/5 overflow-y-auto">
                                {scanLog.map((entry) => (
                                    <div key={entry.id} className="flex items-center gap-3 px-5 py-3">
                                        {entry.success && entry.student?.photo_url ? (
                                            <img src={entry.student.photo_url} alt="" className="size-10 shrink-0 rounded-lg object-cover" />
                                        ) : (
                                            <div
                                                className={`flex size-10 shrink-0 items-center justify-center rounded-lg ${
                                                    entry.success ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'
                                                }`}
                                            >
                                                {entry.success ? <CheckCircle2 className="size-5" /> : <XCircle className="size-5" />}
                                            </div>
                                        )}
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-semibold text-slate-100">
                                                {entry.student?.full_name || entry.message}
                                            </p>
                                            <p className="truncate text-xs text-slate-400">
                                                {entry.student?.classroom}
                                                {entry.student?.type_label ? ` · ${entry.student.type_label}` : ''}
                                                {entry.student?.status ? ` · ${entry.student.status}` : ''}
                                            </p>
                                        </div>
                                        <span className="shrink-0 font-mono text-xs text-slate-500">{entry.time}</span>
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
