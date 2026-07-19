import { Head } from '@inertiajs/react';
import { AlertTriangle, Check, CheckCircle2, Copy, Download, Loader2, User, X } from 'lucide-react';
import { useEffect, useState } from 'react';

type Student = {
    id: string;
    full_name: string;
    nis: string;
    nisn: string | null;
    classroom: string | null;
    photo_url: string | null;
};

type Props = {
    student: Student;
    queued: boolean;
};

type StatusItem = {
    type: 'photo' | 'card' | 'photo_sheet';
    name: string;
    status: 'processing' | 'completed' | 'failed';
    url: string | null;
    thumb_url: string | null;
};

export default function StudentRegisterResult({ student, queued }: Props) {
    const [statusItems, setStatusItems] = useState<StatusItem[]>([]);
    const [statusDone, setStatusDone] = useState(false);

    // Poll generation status until every output is done.
    useEffect(() => {
        if (!queued || statusDone) {
            return;
        }

        let cancelled = false;
        let inFlight = false;

        async function poll() {
            if (inFlight) {
                return;
            }
            inFlight = true;

            try {
                const res = await fetch(`/daftar/status/${student.id}`, {
                    headers: { Accept: 'application/json' },
                });

                if (!res.ok) {
                    return;
                }

                const json = (await res.json()) as { done: boolean; items: StatusItem[] };

                if (!cancelled) {
                    setStatusItems(Array.isArray(json.items) ? json.items : []);

                    if (json.done) {
                        setStatusDone(true);
                    }
                }
            } catch {
                // transient network error — keep polling
            } finally {
                inFlight = false;
            }
        }

        poll();
        const interval = setInterval(poll, 3000);

        return () => {
            cancelled = true;
            clearInterval(interval);
        };
    }, [queued, statusDone, student.id]);

    // Prefer the freshly cropped photo (once ready) for the profile avatar.
    const photoItem = statusItems.find((i) => i.type === 'photo' && i.status === 'completed');
    const avatarUrl = photoItem?.thumb_url ?? student.photo_url;

    return (
        <>
            <Head title={`Hasil Pendaftaran — ${student.full_name}`} />
            <div className="relative flex min-h-screen flex-col bg-zinc-50 dark:bg-zinc-950">
                {/* Grid pattern + gradient glow background */}
                <div aria-hidden="true" className="pointer-events-none absolute inset-0 overflow-hidden">
                    <div
                        className="absolute inset-0 opacity-[0.05] dark:opacity-[0.08] [mask-image:radial-gradient(ellipse_70%_55%_at_50%_0%,black,transparent)]"
                        style={{
                            backgroundImage:
                                'linear-gradient(to right, var(--foreground) 1px, transparent 1px), linear-gradient(to bottom, var(--foreground) 1px, transparent 1px)',
                            backgroundSize: '40px 40px',
                        }}
                    />
                    <div className="absolute -top-32 left-1/2 size-[42rem] -translate-x-1/2 rounded-full bg-green-500/10 blur-[120px] dark:bg-green-500/15" />
                </div>

                <div className="relative z-10 flex flex-1 flex-col">
                    <div className="mx-auto w-full max-w-2xl px-4 py-8">
                        <div className="rounded-2xl border border-green-200 bg-green-50 p-6 dark:border-green-800 dark:bg-green-950">
                            <CheckCircle2 className="mx-auto mb-4 size-16 text-green-600 dark:text-green-400" />
                            <h2 className="mb-2 text-center text-2xl font-bold text-green-800 dark:text-green-200">Pendaftaran Berhasil!</h2>
                            <p className="mb-6 text-center text-green-700 dark:text-green-300">
                                Data siswa berhasil didaftarkan. Simpan halaman ini — link bisa dibuka lagi kapan pun untuk unduh foto & kartu.
                            </p>

                            {/* Student info */}
                            <div className="mb-6 flex items-center gap-4 rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-800">
                                {avatarUrl ? (
                                    <img
                                        src={avatarUrl}
                                        alt={student.full_name}
                                        className="h-24 w-[72px] rounded-xl border-2 border-green-300 object-cover"
                                    />
                                ) : (
                                    <div className="flex h-24 w-[72px] items-center justify-center rounded-xl border-2 border-green-300 bg-green-100 dark:bg-green-900">
                                        <User className="size-8 text-green-400" />
                                    </div>
                                )}
                                <div>
                                    <h3 className="text-lg font-bold">{student.full_name}</h3>
                                    <p className="text-muted-foreground text-sm">
                                        NIS: {student.nis}
                                        {student.nisn && ` · NISN: ${student.nisn}`}
                                    </p>
                                    <p className="text-muted-foreground text-sm">Kelas: {student.classroom}</p>
                                </div>
                            </div>

                            {/* Async generation results */}
                            <div className="space-y-4">
                                {statusDone ? (
                                    <div className="flex items-center gap-3 rounded-xl border border-green-200 bg-green-100/60 p-4 dark:border-green-800 dark:bg-green-900/40">
                                        <CheckCircle2 className="size-5 shrink-0 text-green-600 dark:text-green-400" />
                                        <p className="text-sm font-semibold text-green-800 dark:text-green-200">Semua Selesai!</p>
                                    </div>
                                ) : (
                                    <div className="flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950">
                                        <Loader2 className="mt-0.5 size-5 shrink-0 animate-spin text-blue-600" />
                                        <div>
                                            <p className="text-sm font-semibold text-blue-800 dark:text-blue-200">Foto & kartu sedang diproses…</p>
                                            <p className="text-muted-foreground mt-0.5 text-xs">
                                                Foto siswa, kartu, dan lembar pas foto otomatis dibuat dan tersimpan ke Google Drive.
                                            </p>
                                        </div>
                                    </div>
                                )}

                                {statusItems.length === 0 ? (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        {['Foto Siswa', 'Kartu OSIS', 'Kartu Perpustakaan', 'Lembar Pas Foto 4R'].map((name) => (
                                            <div
                                                key={name}
                                                className="flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800"
                                            >
                                                <div className="flex items-center justify-center bg-zinc-50 p-3 dark:bg-zinc-900" style={{ minHeight: 140 }}>
                                                    <Loader2 className="size-8 animate-spin text-amber-500" />
                                                </div>
                                                <div className="flex items-center justify-between gap-2 p-3">
                                                    <span className="text-sm font-medium">{name}</span>
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">
                                                        <Loader2 className="size-3 animate-spin" /> Diproses
                                                    </span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        {statusItems.map((item, i) => (
                                            <ResultTile key={`${item.type}-${i}`} item={item} />
                                        ))}
                                    </div>
                                )}
                            </div>

                            <div className="mt-6 text-center">
                                <a
                                    href="/daftar"
                                    className="inline-flex items-center justify-center rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-lg shadow-blue-500/25"
                                >
                                    Daftarkan Siswa Lain
                                </a>
                            </div>
                        </div>
                    </div>

                    <footer className="mt-auto border-t border-zinc-200 py-6 text-center dark:border-zinc-800">
                        <p className="text-muted-foreground text-sm">
                            Powered by <span className="font-semibold">Tyas Photo</span>
                        </p>
                    </footer>
                </div>
            </div>
        </>
    );
}

function ResultTile({ item }: { item: StatusItem }) {
    const [copied, setCopied] = useState(false);

    async function copyLink() {
        if (!item.url) {
            return;
        }

        try {
            await navigator.clipboard.writeText(item.url);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // clipboard unavailable — ignore
        }
    }

    return (
        <div className="flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            {/* Thumbnail / placeholder */}
            <div className="flex items-center justify-center bg-zinc-50 p-3 dark:bg-zinc-900" style={{ minHeight: 140 }}>
                {item.status === 'completed' && (item.thumb_url ?? item.url) ? (
                    <img src={item.thumb_url ?? item.url ?? undefined} alt={item.name} className="max-h-[200px] w-full object-contain" />
                ) : item.status === 'processing' ? (
                    <Loader2 className="size-8 animate-spin text-amber-500" />
                ) : (
                    <AlertTriangle className="size-8 text-red-500" />
                )}
            </div>

            {/* Meta + actions */}
            <div className="flex flex-1 flex-col gap-2 p-3">
                <div className="flex items-center justify-between gap-2">
                    <span className="text-sm font-medium">{item.name}</span>
                    {item.status === 'processing' && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">
                            <Loader2 className="size-3 animate-spin" /> Diproses
                        </span>
                    )}
                    {item.status === 'completed' && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/50 dark:text-green-300">
                            <Check className="size-3" /> Selesai
                        </span>
                    )}
                    {item.status === 'failed' && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/50 dark:text-red-300">
                            <X className="size-3" /> Gagal
                        </span>
                    )}
                </div>

                {item.status === 'completed' && item.url && (
                    <div className="mt-auto flex gap-2">
                        <button
                            type="button"
                            onClick={copyLink}
                            className="inline-flex flex-1 items-center justify-center gap-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-700"
                        >
                            {copied ? (
                                <>
                                    <Check className="size-3.5 text-green-600" /> Tersalin!
                                </>
                            ) : (
                                <>
                                    <Copy className="size-3.5" /> Salin Link
                                </>
                            )}
                        </button>
                        <a
                            href={item.thumb_url ?? item.url}
                            download
                            className="inline-flex flex-1 items-center justify-center gap-1 rounded-lg bg-blue-600 px-2 py-1.5 text-xs font-medium text-white hover:bg-blue-700"
                        >
                            <Download className="size-3.5" /> Unduh
                        </a>
                    </div>
                )}
            </div>
        </div>
    );
}
