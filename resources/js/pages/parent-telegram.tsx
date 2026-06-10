import { Head, usePage } from '@inertiajs/react';
import { ArrowLeft, BadgeCheck, CheckCircle2, Loader2, Phone, School as SchoolIcon, Search, Send, Sparkles, User } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { SimpleCaptcha } from '@/components/simple-captcha';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';

type School = { id: string; name: string; city: string | null };

type StudentResult = {
    id: string;
    full_name: string;
    nis: string | null;
    classroom: { name: string } | null;
    school: { name: string } | null;
    parent_profile: { telegram_chat_id: string | null } | null;
};

export default function ParentTelegram({ schools }: { schools: School[] }) {
    const [schoolId, setSchoolId] = useState('');
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<StudentResult[]>([]);
    const [searching, setSearching] = useState(false);
    const [showDropdown, setShowDropdown] = useState(false);
    const [selected, setSelected] = useState<StudentResult | null>(null);

    const [whatsappNumber, setWhatsappNumber] = useState('');
    const [chatId, setChatId] = useState('');
    const [captchaVerified, setCaptchaVerified] = useState(false);

    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState<{ name: string; classroom: string | null } | null>(null);

    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const boxRef = useRef<HTMLDivElement>(null);

    const csrfToken = typeof document !== 'undefined'
        ? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || ''
        : '';

    // Debounced student search via public API, scoped to the chosen school.
    useEffect(() => {
        if (!schoolId) {
            setResults([]);
            setShowDropdown(false);
            return;
        }
        if (selected && query === selected.full_name) {
            return;
        }
        if (query.trim().length < 2) {
            setResults([]);
            setShowDropdown(false);
            return;
        }

        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }
        debounceRef.current = setTimeout(async () => {
            setSearching(true);
            try {
                const res = await fetch(`/api/students?school_id=${encodeURIComponent(schoolId)}&search=${encodeURIComponent(query.trim())}&per_page=8`, {
                    headers: { Accept: 'application/json' },
                });
                const json = await res.json();
                setResults(json.data ?? []);
                setShowDropdown(true);
            } catch {
                setResults([]);
            } finally {
                setSearching(false);
            }
        }, 350);

        return () => {
            if (debounceRef.current) {
                clearTimeout(debounceRef.current);
            }
        };
    }, [query, selected, schoolId]);

    // Close dropdown on outside click.
    useEffect(() => {
        function onClick(e: MouseEvent) {
            if (boxRef.current && !boxRef.current.contains(e.target as Node)) {
                setShowDropdown(false);
            }
        }
        document.addEventListener('mousedown', onClick);
        return () => document.removeEventListener('mousedown', onClick);
    }, []);

    function pickStudent(student: StudentResult) {
        setSelected(student);
        setQuery(student.full_name);
        setShowDropdown(false);
        setError('');
    }

    const handleSubmit = useCallback(async (e: React.FormEvent) => {
        e.preventDefault();
        if (!selected) {
            setError('Pilih nama siswa terlebih dahulu.');
            return;
        }
        setSubmitting(true);
        setError('');

        try {
            const res = await fetch('/daftar-telegram', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    school_id: schoolId,
                    student_id: selected.id,
                    whatsapp_number: whatsappNumber,
                    telegram_chat_id: chatId,
                }),
            });
            const json = await res.json();

            if (res.ok && json.success) {
                setSuccess({ name: json.student?.full_name ?? selected.full_name, classroom: json.student?.classroom ?? null });
            } else if (res.status === 422 && json.errors) {
                setError(Object.values(json.errors).flat()[0] as string);
            } else {
                setError(json.message || 'Gagal menyimpan. Coba lagi.');
            }
        } catch {
            setError('Gagal menghubungi server.');
        } finally {
            setSubmitting(false);
        }
    }, [selected, whatsappNumber, chatId, csrfToken, schoolId]);

    function resetForm() {
        setSuccess(null);
        setSelected(null);
        setQuery('');
        setWhatsappNumber('');
        setChatId('');
        setCaptchaVerified(false);
        setError('');
    }

    return (
        <Shell>
            <div className="grid w-full max-w-5xl overflow-hidden rounded-3xl border border-white/60 bg-white/80 shadow-2xl shadow-sky-900/10 backdrop-blur-xl lg:grid-cols-[1fr_1.1fr] dark:border-white/10 dark:bg-zinc-900/70">
                {/* Brand / info panel */}
                <aside className="relative hidden flex-col justify-between overflow-hidden bg-gradient-to-br from-sky-500 via-blue-600 to-indigo-700 p-10 text-white lg:flex">
                    <div className="pointer-events-none absolute -top-16 -right-16 size-56 rounded-full bg-white/10 blur-2xl" />
                    <div className="pointer-events-none absolute -bottom-20 -left-10 size-64 rounded-full bg-white/10 blur-3xl" />

                    <div className="relative">
                        <div className="flex size-12 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur">
                            <Send className="size-6" />
                        </div>
                        <h2 className="mt-6 text-3xl font-bold leading-tight">Notifikasi Kehadiran via Telegram</h2>
                        <p className="mt-3 text-sm text-white/80">
                            Pantau kehadiran putra/putri Anda secara real-time. Setiap kali mereka absen masuk atau pulang, pesan langsung dikirim ke Telegram Anda.
                        </p>
                    </div>

                    <ul className="relative mt-8 space-y-4 text-sm">
                        {[
                            'Pilih sekolah & cari nama anak',
                            'Verifikasi dengan nomor WhatsApp terdaftar',
                            'Tempel Chat ID Telegram Anda',
                        ].map((step, i) => (
                            <li key={i} className="flex items-center gap-3">
                                <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-white/20 text-xs font-bold ring-1 ring-white/30">
                                    {i + 1}
                                </span>
                                <span className="text-white/90">{step}</span>
                            </li>
                        ))}
                    </ul>

                    <div className="relative mt-8 flex items-center gap-2 text-xs text-white/70">
                        <BadgeCheck className="size-4" />
                        Aman — terverifikasi nomor WhatsApp orang tua.
                    </div>
                </aside>

                {/* Form panel */}
                <main className="p-6 sm:p-10">
                    {success ? (
                        <div className="flex h-full flex-col items-center justify-center py-8 text-center">
                            <div className="flex size-20 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/40">
                                <CheckCircle2 className="size-11 text-green-600 dark:text-green-400" />
                            </div>
                            <h2 className="mt-6 text-2xl font-bold">Berhasil Terhubung!</h2>
                            <p className="text-muted-foreground mt-2 max-w-sm text-sm">
                                Telegram untuk <b className="text-foreground">{success.name}</b>
                                {success.classroom && ` (${success.classroom})`} sudah aktif. Notifikasi kehadiran akan dikirim ke Telegram Anda.
                            </p>
                            <Button className="mt-8 w-full max-w-xs" variant="outline" onClick={resetForm}>
                                Daftarkan Siswa Lain
                            </Button>
                        </div>
                    ) : (
                        <>
                            <div className="mb-7">
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-sky-100 px-3 py-1 text-xs font-medium text-sky-700 dark:bg-sky-950 dark:text-sky-300">
                                    <Sparkles className="size-3" /> Gratis untuk orang tua
                                </span>
                                <h1 className="mt-3 text-2xl font-bold sm:text-3xl">Hubungkan Telegram</h1>
                                <p className="text-muted-foreground mt-1.5 text-sm">
                                    Cari nama putra/putri Anda, lalu masukkan Chat ID Telegram untuk menerima notifikasi kehadiran.
                                </p>
                            </div>

                            {schools.length === 0 ? (
                                <div className="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                    Belum ada sekolah yang mengaktifkan notifikasi Telegram. Hubungi admin sekolah Anda.
                                </div>
                            ) : (
                                <form onSubmit={handleSubmit} className="space-y-5">
                                    {/* School select */}
                                    <div className="grid gap-2">
                                        <Label className="flex items-center gap-1.5"><SchoolIcon className="size-3.5" /> Sekolah</Label>
                                        <Select
                                            value={schoolId}
                                            onValueChange={(v) => {
                                                setSchoolId(v);
                                                setSelected(null);
                                                setQuery('');
                                                setResults([]);
                                            }}
                                        >
                                            <SelectTrigger className="h-11">
                                                <SelectValue placeholder="Pilih sekolah..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {schools.map((s) => (
                                                    <SelectItem key={s.id} value={s.id}>
                                                        {s.name}{s.city ? ` — ${s.city}` : ''}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    {/* Student search */}
                                    <div className="grid gap-2" ref={boxRef}>
                                        <Label className="flex items-center gap-1.5"><User className="size-3.5" /> Nama Siswa</Label>
                                        <div className="relative">
                                            <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                            <Input
                                                value={query}
                                                onChange={(e) => {
                                                    setQuery(e.target.value);
                                                    setSelected(null);
                                                }}
                                                onFocus={() => results.length > 0 && setShowDropdown(true)}
                                                placeholder={schoolId ? 'Ketik nama siswa...' : 'Pilih sekolah dulu'}
                                                className="h-11 pl-9"
                                                autoComplete="off"
                                                disabled={!schoolId}
                                            />
                                            {searching && <Loader2 className="text-muted-foreground absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin" />}

                                            {showDropdown && results.length > 0 && (
                                                <div className="absolute z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-800">
                                                    {results.map((s) => (
                                                        <button
                                                            key={s.id}
                                                            type="button"
                                                            onClick={() => pickStudent(s)}
                                                            className="flex w-full items-start gap-3 border-b border-zinc-100 px-4 py-3 text-left transition last:border-0 hover:bg-sky-50 dark:border-zinc-700/50 dark:hover:bg-zinc-700/50"
                                                        >
                                                            <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-600 dark:bg-sky-950 dark:text-sky-300">
                                                                <User className="size-4" />
                                                            </span>
                                                            <div className="min-w-0">
                                                                <p className="truncate font-medium">{s.full_name}</p>
                                                                <p className="text-muted-foreground flex items-center gap-1 truncate text-xs">
                                                                    <SchoolIcon className="size-3" />
                                                                    {s.school?.name ?? '—'}
                                                                    {s.classroom && ` · ${s.classroom.name}`}
                                                                </p>
                                                            </div>
                                                            {s.parent_profile?.telegram_chat_id && (
                                                                <span className="ml-auto shrink-0 rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-medium text-green-700 dark:bg-green-900 dark:text-green-300">
                                                                    sudah aktif
                                                                </span>
                                                            )}
                                                        </button>
                                                    ))}
                                                </div>
                                            )}
                                            {showDropdown && !searching && query.trim().length >= 2 && results.length === 0 && (
                                                <div className="text-muted-foreground absolute z-20 mt-1 w-full rounded-xl border border-zinc-200 bg-white p-4 text-center text-sm shadow-xl dark:border-zinc-700 dark:bg-zinc-800">
                                                    Siswa tidak ditemukan.
                                                </div>
                                            )}
                                        </div>
                                        {selected && (
                                            <p className="flex items-center gap-1 text-xs text-green-600 dark:text-green-400">
                                                <CheckCircle2 className="size-3.5" /> Dipilih: <b>{selected.full_name}</b>
                                                {selected.school && ` — ${selected.school.name}`}
                                            </p>
                                        )}
                                    </div>

                                    {/* WhatsApp verification */}
                                    <div className="grid gap-2">
                                        <Label className="flex items-center gap-1.5"><Phone className="size-3.5" /> Nomor WhatsApp Orang Tua</Label>
                                        <Input
                                            value={whatsappNumber}
                                            onChange={(e) => setWhatsappNumber(e.target.value)}
                                            placeholder="08xxxxxxxxxx"
                                            inputMode="tel"
                                            className="h-11"
                                        />
                                        <p className="text-muted-foreground text-xs">
                                            Untuk verifikasi. Harus sama dengan nomor yang terdaftar di sekolah.
                                        </p>
                                    </div>

                                    {/* Telegram chat id */}
                                    <div className="grid gap-2">
                                        <Label className="flex items-center gap-1.5"><Send className="size-3.5" /> Telegram Chat ID</Label>
                                        <Input
                                            value={chatId}
                                            onChange={(e) => setChatId(e.target.value)}
                                            placeholder="mis. 123456789"
                                            inputMode="numeric"
                                            className="h-11"
                                        />
                                        <div className="rounded-xl border border-sky-100 bg-sky-50 p-3 text-xs text-sky-800 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200">
                                            <p className="mb-1 font-semibold">Cara mendapatkan Chat ID:</p>
                                            <ol className="list-inside list-decimal space-y-0.5">
                                                <li>Cari & buka bot Telegram sekolah, tekan <b>Start</b>.</li>
                                                <li>Buka chat <b>@userinfobot</b> di Telegram.</li>
                                                <li>Salin angka <b>Id</b> yang muncul, tempel di sini.</li>
                                            </ol>
                                        </div>
                                    </div>

                                    {/* Captcha */}
                                    <SimpleCaptcha onVerified={(token) => setCaptchaVerified(!!token)} />

                                    {error && (
                                        <div className="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-300">
                                            {error}
                                        </div>
                                    )}

                                    <Button
                                        type="submit"
                                        disabled={submitting || !captchaVerified || !selected}
                                        className="h-12 w-full bg-gradient-to-r from-sky-500 to-blue-600 text-base font-semibold text-white shadow-lg shadow-blue-500/25 hover:from-sky-600 hover:to-blue-700 disabled:opacity-50"
                                    >
                                        {submitting ? <Spinner /> : (<><Send className="mr-2 size-4" /> Hubungkan Telegram</>)}
                                    </Button>
                                </form>
                            )}
                        </>
                    )}
                </main>
            </div>
        </Shell>
    );
}

function Shell({ children }: { children: React.ReactNode }) {
    const { name } = usePage().props as { name: string };

    return (
        <>
            <Head title="Hubungkan Telegram" />
            <div className="relative min-h-screen overflow-hidden bg-gradient-to-br from-sky-50 via-white to-indigo-50 dark:from-zinc-950 dark:via-zinc-950 dark:to-zinc-900">
                {/* Decorative blobs */}
                <div className="pointer-events-none absolute -top-24 -left-24 size-72 rounded-full bg-sky-300/30 blur-3xl dark:bg-sky-900/20" />
                <div className="pointer-events-none absolute -right-24 bottom-0 size-72 rounded-full bg-indigo-300/30 blur-3xl dark:bg-indigo-900/20" />

                {/* Top nav */}
                <header className="relative z-10 mx-auto flex h-16 max-w-5xl items-center justify-between px-4 sm:px-6">
                    <a href="/" className="flex items-center gap-2.5 font-bold">
                        <div className="flex size-8 items-center justify-center rounded-lg bg-gradient-to-br from-sky-500 to-blue-600 shadow">
                            <AppLogoIcon className="size-4.5 fill-current text-white" />
                        </div>
                        <span className="text-lg tracking-tight">{name}</span>
                    </a>
                    <Button variant="ghost" size="sm" asChild>
                        <a href="/">
                            <ArrowLeft className="mr-1.5 size-4" /> Beranda
                        </a>
                    </Button>
                </header>

                <div className="relative z-10 flex items-center justify-center px-4 pt-2 pb-16 sm:px-6">
                    {children}
                </div>
            </div>
        </>
    );
}
