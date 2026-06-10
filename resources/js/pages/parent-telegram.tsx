import { Head } from '@inertiajs/react';
import { CheckCircle2, Loader2, MessageCircle, School, Search, Send, User } from 'lucide-react';
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

    if (success) {
        return (
            <Wrapper>
                <div className="mx-auto max-w-md px-4 py-16">
                    <div className="rounded-2xl border border-green-200 bg-green-50 p-6 text-center dark:border-green-800 dark:bg-green-950">
                        <CheckCircle2 className="mx-auto mb-4 size-16 text-green-600 dark:text-green-400" />
                        <h2 className="mb-2 text-2xl font-bold text-green-800 dark:text-green-200">Berhasil Terhubung!</h2>
                        <p className="text-green-700 dark:text-green-300">
                            Telegram untuk <b>{success.name}</b>
                            {success.classroom && ` (${success.classroom})`} sudah aktif. Notifikasi kehadiran akan dikirim ke Telegram Anda.
                        </p>
                    </div>
                    <Button
                        className="mt-6 w-full"
                        variant="outline"
                        onClick={() => {
                            setSuccess(null);
                            setSelected(null);
                            setQuery('');
                            setWhatsappNumber('');
                            setChatId('');
                            setCaptchaVerified(false);
                        }}
                    >
                        Daftarkan Siswa Lain
                    </Button>
                </div>
            </Wrapper>
        );
    }

    return (
        <Wrapper>
            <div className="mx-auto max-w-lg px-4 py-12">
                <div className="mb-8 text-center">
                    <div className="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-500 to-blue-600 shadow-lg">
                        <MessageCircle className="size-7 text-white" />
                    </div>
                    <h1 className="text-2xl font-bold">Hubungkan Telegram</h1>
                    <p className="text-muted-foreground mt-2 text-sm">
                        Cari nama putra/putri Anda, lalu masukkan Chat ID Telegram untuk menerima notifikasi kehadiran.
                    </p>
                </div>

                {schools.length === 0 ? (
                    <div className="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                        Belum ada sekolah yang mengaktifkan notifikasi Telegram. Hubungi admin sekolah Anda.
                    </div>
                ) : (
                <form onSubmit={handleSubmit} className="space-y-6 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    {/* School select */}
                    <div className="grid gap-2">
                        <Label>Sekolah</Label>
                        <Select
                            value={schoolId}
                            onValueChange={(v) => {
                                setSchoolId(v);
                                setSelected(null);
                                setQuery('');
                                setResults([]);
                            }}
                        >
                            <SelectTrigger>
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
                        <p className="text-muted-foreground text-xs">Hanya sekolah dengan notifikasi Telegram aktif yang tampil.</p>
                    </div>

                    {/* Student search */}
                    <div className="grid gap-2" ref={boxRef}>
                        <Label>Nama Siswa</Label>
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
                                className="pl-9"
                                autoComplete="off"
                                disabled={!schoolId}
                            />
                            {searching && <Loader2 className="text-muted-foreground absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin" />}

                            {showDropdown && results.length > 0 && (
                                <div className="absolute z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                                    {results.map((s) => (
                                        <button
                                            key={s.id}
                                            type="button"
                                            onClick={() => pickStudent(s)}
                                            className="flex w-full items-start gap-3 border-b border-zinc-100 px-4 py-3 text-left transition last:border-0 hover:bg-zinc-50 dark:border-zinc-700/50 dark:hover:bg-zinc-700/50"
                                        >
                                            <User className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                                            <div className="min-w-0">
                                                <p className="truncate font-medium">{s.full_name}</p>
                                                <p className="text-muted-foreground flex items-center gap-1 truncate text-xs">
                                                    <School className="size-3" />
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
                                <div className="absolute z-20 mt-1 w-full rounded-xl border border-zinc-200 bg-white p-4 text-center text-sm text-muted-foreground shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                                    Siswa tidak ditemukan.
                                </div>
                            )}
                        </div>
                        {selected && (
                            <p className="text-xs text-green-600 dark:text-green-400">
                                Dipilih: <b>{selected.full_name}</b>
                                {selected.school && ` — ${selected.school.name}`}
                            </p>
                        )}
                    </div>

                    {/* WhatsApp verification */}
                    <div className="grid gap-2">
                        <Label>Nomor WhatsApp Orang Tua</Label>
                        <Input
                            value={whatsappNumber}
                            onChange={(e) => setWhatsappNumber(e.target.value)}
                            placeholder="08xxxxxxxxxx"
                            inputMode="tel"
                        />
                        <p className="text-muted-foreground text-xs">
                            Untuk verifikasi. Harus sama dengan nomor yang terdaftar di sekolah.
                        </p>
                    </div>

                    {/* Telegram chat id */}
                    <div className="grid gap-2">
                        <Label>Telegram Chat ID</Label>
                        <Input
                            value={chatId}
                            onChange={(e) => setChatId(e.target.value)}
                            placeholder="mis. 123456789"
                            inputMode="numeric"
                        />
                        <div className="rounded-lg bg-sky-50 p-3 text-xs text-sky-800 dark:bg-sky-950 dark:text-sky-200">
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
                        className="h-12 w-full bg-gradient-to-r from-sky-500 to-blue-600 text-base font-semibold text-white shadow-lg shadow-blue-500/25 disabled:opacity-50"
                    >
                        {submitting ? <Spinner /> : (<><Send className="mr-2 size-4" /> Hubungkan Telegram</>)}
                    </Button>
                </form>
                )}
            </div>
        </Wrapper>
    );
}

function Wrapper({ children }: { children: React.ReactNode }) {
    return (
        <>
            <Head title="Hubungkan Telegram" />
            <div className="min-h-screen bg-zinc-50 dark:bg-zinc-950">
                <div className="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                    <div className="mx-auto flex h-16 max-w-7xl items-center px-4">
                        <a href="/" className="flex items-center gap-2.5 font-bold">
                            <div className="flex size-8 items-center justify-center rounded-lg bg-gradient-to-br from-blue-600 to-indigo-600">
                                <AppLogoIcon className="size-4.5 fill-current text-white" />
                            </div>
                        </a>
                    </div>
                </div>
                {children}
            </div>
        </>
    );
}
