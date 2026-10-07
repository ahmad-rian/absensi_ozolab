import { usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    ChevronDown,
    Loader2,
    Search,
    Send,
    User,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useCallback, useEffect, useRef, useState } from 'react';
import '../../css/depan.css';
import BrandLogo from '@/components/brand-logo';
import { FontDepan } from '@/components/depan/font-depan';
import { Awan, KertasSobek } from '@/components/depan/kolase';
import { Muncul } from '@/components/depan/muncul';
import { SimpleCaptcha } from '@/components/simple-captcha';
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

const LANGKAH = [
    'Pilih sekolah dan cari nama anak',
    'Verifikasi nomor WhatsApp terdaftar',
    'Tempel Chat ID Telegram',
];

const KOTAK =
    'h-12 w-full rounded-xl border border-[var(--garis)] bg-[var(--dasar)] px-4 text-[0.95rem] text-[var(--tinta)] outline-none transition placeholder:text-[var(--tinta-3)] focus:border-[var(--biru)] focus:ring-2 focus:ring-[var(--biru)]/20 disabled:opacity-50';

function Kolom({
    label,
    catatan,
    children,
}: {
    label: string;
    catatan?: string;
    children: ReactNode;
}) {
    return (
        <label className="grid gap-2">
            <span className="text-sm font-semibold">{label}</span>
            {children}
            {catatan && (
                <span className="text-xs text-[var(--tinta-3)]">{catatan}</span>
            )}
        </label>
    );
}

/** Catatan cara mendapatkan Chat ID, sebagai kertas bergaris yang ditempel. */
function CatatanChatId({ className = '' }: { className?: string }) {
    return (
        <div
            className={`bergaris rounded-md p-5 text-sm leading-relaxed text-[#1d1b19] shadow-[0_18px_36px_-20px_rgb(29_27_25/0.6)] ${className}`}
        >
            <p className="serif text-xl">Cara mendapatkan Chat ID</p>
            <ol className="mt-2 list-inside list-decimal space-y-1">
                <li>
                    Buka bot Telegram sekolah, tekan <b>Start</b>.
                </li>
                <li>
                    Buka chat <b>@userinfobot</b>.
                </li>
                <li>
                    Salin angka <b>Id</b>, tempel di formulir.
                </li>
            </ol>
        </div>
    );
}

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
    const [success, setSuccess] = useState<{
        name: string;
        classroom: string | null;
    } | null>(null);

    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const boxRef = useRef<HTMLDivElement>(null);

    const csrfToken =
        typeof document !== 'undefined'
            ? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                  ?.content || ''
            : '';

    // Pencarian siswa lewat API publik, dibatasi sekolah yang dipilih.
    // Hasil lama dibersihkan di handler input, bukan di sini: effect ini
    // hanya menjadwalkan pencarian.
    useEffect(() => {
        if (
            !schoolId ||
            query.trim().length < 2 ||
            (selected && query === selected.full_name)
        ) {
            return;
        }

        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        debounceRef.current = setTimeout(async () => {
            setSearching(true);

            try {
                const res = await fetch(
                    `/api/students?school_id=${encodeURIComponent(schoolId)}&search=${encodeURIComponent(query.trim())}&per_page=8`,
                    { headers: { Accept: 'application/json' } },
                );
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

    const handleSubmit = useCallback(
        async (e: FormEvent) => {
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
                    setSuccess({
                        name: json.student?.full_name ?? selected.full_name,
                        classroom: json.student?.classroom ?? null,
                    });
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
        },
        [selected, whatsappNumber, chatId, csrfToken, schoolId],
    );

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
        <Kerangka>
            <div className="relative mx-auto -mt-40 max-w-5xl px-3 sm:-mt-48 sm:px-6">
                <CatatanChatId className="absolute top-24 -left-6 hidden w-64 -rotate-3 xl:block" />

                <Muncul
                    gaya="cetak"
                    miring="0deg"
                    className="relative mx-auto max-w-xl"
                >
                    <div className="rounded-[24px] border border-[var(--garis)] bg-[var(--kartu)] p-6 shadow-[0_30px_70px_-40px_rgb(29_27_25/0.55)] sm:p-9">
                        {success ? (
                            <div className="py-6 text-center">
                                <div className="mx-auto grid size-16 place-items-center rounded-full bg-[var(--hijau-muda)] text-[var(--hijau)]">
                                    <Check
                                        className="size-8"
                                        strokeWidth={2.2}
                                    />
                                </div>
                                <h2 className="serif mt-5 text-4xl">
                                    Telegram terhubung
                                </h2>
                                <p className="mx-auto mt-3 max-w-sm text-[var(--tinta-2)]">
                                    Kabar kehadiran{' '}
                                    <b className="text-[var(--tinta)]">
                                        {success.name}
                                    </b>
                                    {success.classroom &&
                                        ` (${success.classroom})`}{' '}
                                    sekarang dikirim ke Telegram Anda.
                                </p>
                                <button
                                    type="button"
                                    onClick={resetForm}
                                    className="mt-8 rounded-full border border-[var(--garis)] px-6 py-3 font-semibold transition hover:border-[var(--tinta-3)]"
                                >
                                    Hubungkan anak lain
                                </button>
                            </div>
                        ) : schools.length === 0 ? (
                            <p className="rounded-2xl bg-[var(--kuning-muda)] p-6 text-center text-sm text-[var(--kuning)]">
                                Belum ada sekolah yang mengaktifkan notifikasi
                                Telegram. Hubungi admin sekolah Anda.
                            </p>
                        ) : (
                            <form onSubmit={handleSubmit} className="space-y-6">
                                <Kolom label="Sekolah">
                                    <div className="relative">
                                        <select
                                            value={schoolId}
                                            onChange={(e) => {
                                                setSchoolId(e.target.value);
                                                setSelected(null);
                                                setQuery('');
                                                setResults([]);
                                            }}
                                            className={`${KOTAK} appearance-none pr-10 ${schoolId ? '' : 'text-[var(--tinta-3)]'}`}
                                        >
                                            <option value="" disabled>
                                                Pilih sekolah
                                            </option>
                                            {schools.map((s) => (
                                                <option
                                                    key={s.id}
                                                    value={s.id}
                                                    className="text-[var(--tinta)]"
                                                >
                                                    {s.name}
                                                    {s.city
                                                        ? ` — ${s.city}`
                                                        : ''}
                                                </option>
                                            ))}
                                        </select>
                                        <ChevronDown className="pointer-events-none absolute top-1/2 right-4 size-4 -translate-y-1/2 text-[var(--tinta-3)]" />
                                    </div>
                                </Kolom>

                                <div className="grid gap-2" ref={boxRef}>
                                    <span className="text-sm font-semibold">
                                        Nama siswa
                                    </span>
                                    <div className="relative">
                                        <Search className="absolute top-1/2 left-4 size-4 -translate-y-1/2 text-[var(--tinta-3)]" />
                                        <input
                                            aria-label="Nama siswa"
                                            value={query}
                                            onChange={(e) => {
                                                setQuery(e.target.value);
                                                setSelected(null);

                                                if (
                                                    e.target.value.trim()
                                                        .length < 2
                                                ) {
                                                    setResults([]);
                                                    setShowDropdown(false);
                                                }
                                            }}
                                            onFocus={() =>
                                                results.length > 0 &&
                                                setShowDropdown(true)
                                            }
                                            placeholder={
                                                schoolId
                                                    ? 'Ketik nama siswa'
                                                    : 'Pilih sekolah dulu'
                                            }
                                            className={`${KOTAK} pl-11`}
                                            autoComplete="off"
                                            disabled={!schoolId}
                                        />
                                        {searching && (
                                            <Loader2 className="absolute top-1/2 right-4 size-4 -translate-y-1/2 animate-spin text-[var(--tinta-3)]" />
                                        )}

                                        {showDropdown && results.length > 0 && (
                                            <div className="absolute z-20 mt-2 max-h-72 w-full overflow-y-auto rounded-2xl border border-[var(--garis)] bg-[var(--kartu)] p-1.5 shadow-[0_24px_50px_-24px_rgb(29_27_25/0.5)]">
                                                {results.map((s) => (
                                                    <button
                                                        key={s.id}
                                                        type="button"
                                                        onClick={() =>
                                                            pickStudent(s)
                                                        }
                                                        className="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition hover:bg-[var(--dasar)]"
                                                    >
                                                        <span className="grid size-8 shrink-0 place-items-center rounded-full bg-[var(--mint)] text-[#1d1b19]">
                                                            <User className="size-4" />
                                                        </span>
                                                        <span className="min-w-0 flex-1">
                                                            <span className="block truncate font-medium">
                                                                {s.full_name}
                                                            </span>
                                                            <span className="block truncate text-xs text-[var(--tinta-3)]">
                                                                {s.school
                                                                    ?.name ??
                                                                    '—'}
                                                                {s.classroom &&
                                                                    ` · ${s.classroom.name}`}
                                                            </span>
                                                        </span>
                                                        {s.parent_profile
                                                            ?.telegram_chat_id && (
                                                            <span className="shrink-0 rounded-full bg-[var(--hijau-muda)] px-2 py-0.5 text-[0.65rem] font-semibold text-[var(--hijau)]">
                                                                sudah aktif
                                                            </span>
                                                        )}
                                                    </button>
                                                ))}
                                            </div>
                                        )}
                                        {showDropdown &&
                                            !searching &&
                                            query.trim().length >= 2 &&
                                            results.length === 0 && (
                                                <div className="absolute z-20 mt-2 w-full rounded-2xl border border-[var(--garis)] bg-[var(--kartu)] p-4 text-center text-sm text-[var(--tinta-3)]">
                                                    Siswa tidak ditemukan.
                                                </div>
                                            )}
                                    </div>
                                    {selected && (
                                        <p className="flex items-center gap-1.5 text-xs text-[var(--hijau)]">
                                            <Check className="size-3.5" />{' '}
                                            {selected.full_name}
                                            {selected.classroom &&
                                                ` · ${selected.classroom.name}`}
                                        </p>
                                    )}
                                </div>

                                <Kolom
                                    label="Nomor WhatsApp orang tua"
                                    catatan="Untuk verifikasi. Harus sama dengan nomor yang terdaftar di sekolah."
                                >
                                    <input
                                        value={whatsappNumber}
                                        onChange={(e) =>
                                            setWhatsappNumber(e.target.value)
                                        }
                                        placeholder="08xxxxxxxxxx"
                                        inputMode="tel"
                                        className={KOTAK}
                                    />
                                </Kolom>

                                <Kolom label="Chat ID Telegram">
                                    <input
                                        value={chatId}
                                        onChange={(e) =>
                                            setChatId(e.target.value)
                                        }
                                        placeholder="mis. 123456789"
                                        inputMode="numeric"
                                        className={KOTAK}
                                    />
                                </Kolom>
                                <CatatanChatId className="-rotate-1 xl:hidden" />

                                <SimpleCaptcha
                                    onVerified={(token) =>
                                        setCaptchaVerified(!!token)
                                    }
                                />

                                {error && (
                                    <p
                                        role="alert"
                                        className="rounded-xl bg-[var(--kuning-muda)] p-3 text-sm text-[var(--kuning)]"
                                    >
                                        {error}
                                    </p>
                                )}

                                <button
                                    type="submit"
                                    disabled={
                                        submitting ||
                                        !captchaVerified ||
                                        !selected
                                    }
                                    className="flex h-12 w-full items-center justify-center gap-2 rounded-full bg-[var(--tinta)] font-semibold text-[var(--dasar)] transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    {submitting ? (
                                        <Spinner />
                                    ) : (
                                        <>
                                            <Send className="size-4" />{' '}
                                            Hubungkan Telegram
                                        </>
                                    )}
                                </button>
                            </form>
                        )}
                    </div>
                </Muncul>
            </div>
        </Kerangka>
    );
}

function Kerangka({ children }: { children: ReactNode }) {
    const { name } = usePage().props as { name: string };

    return (
        <div className="depan min-h-screen pb-20">
            <FontDepan judul="Hubungkan Telegram" />

            <header className="fixed inset-x-0 top-3 z-50 flex justify-center px-3 sm:top-5">
                <div className="flex h-14 w-full max-w-3xl items-center justify-between rounded-full border border-[var(--garis)] bg-[var(--kartu)]/90 pr-2 pl-4 shadow-[0_6px_18px_-12px_rgb(29_27_25/0.35)] backdrop-blur-md">
                    <a href="/" className="flex items-center gap-2.5 font-bold">
                        <BrandLogo className="size-7 rounded-md" />
                        <span className="tracking-tight">{name}</span>
                    </a>
                    <a
                        href="/"
                        className="flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold transition hover:bg-[var(--dasar)]"
                    >
                        <ArrowLeft className="size-4" /> Beranda
                    </a>
                </div>
            </header>

            <section className="px-2 pt-2 sm:px-3 sm:pt-3">
                <div className="langit relative isolate overflow-hidden rounded-[28px] px-5 pt-32 pb-60 text-center sm:pt-36 sm:pb-72">
                    <div className="halftone absolute inset-0 -z-10 [mask-image:linear-gradient(to_bottom,black,transparent_75%)] opacity-60" />
                    <Awan className="top-24 -left-10 -z-10 h-24 w-64 max-sm:h-16 max-sm:w-40" />
                    <Awan className="top-16 -right-12 -z-10 h-28 w-72 max-sm:hidden" />

                    <Muncul
                        as="h1"
                        className="serif mx-auto max-w-2xl text-[clamp(2.4rem,5.6vw,4.4rem)] leading-[1.03]"
                    >
                        Kabar kehadiran anak, <em>langsung di Telegram</em>
                    </Muncul>
                    <Muncul
                        as="p"
                        tunda={150}
                        className="mx-auto mt-5 max-w-lg leading-relaxed text-[var(--tinta-2)]"
                    >
                        Setiap kali anak Anda absen masuk atau pulang, pesannya
                        dikirim ke Telegram. Gratis untuk orang tua.
                    </Muncul>
                    <Muncul
                        as="ol"
                        tunda={260}
                        className="mx-auto mt-8 flex max-w-2xl flex-wrap justify-center gap-2"
                    >
                        {LANGKAH.map((l, i) => (
                            <li
                                key={l}
                                className="flex items-center gap-2 rounded-full bg-[var(--kartu)]/80 py-1.5 pr-4 pl-1.5 text-sm backdrop-blur"
                            >
                                <span className="grid size-6 place-items-center rounded-full bg-[var(--tinta)] text-xs font-bold text-[var(--dasar)]">
                                    {i + 1}
                                </span>
                                {l}
                            </li>
                        ))}
                    </Muncul>

                    <KertasSobek
                        benih={83}
                        kasar={18}
                        className="halftone bottom-0 left-0 -z-10 h-36 w-[48%] bg-[var(--ungu)]"
                    />
                    <KertasSobek
                        benih={89}
                        kasar={22}
                        className="right-0 bottom-0 -z-10 h-44 w-[30%] bg-[var(--arang)]"
                    />
                    <KertasSobek
                        benih={97}
                        kasar={10}
                        className="bergaris right-0 bottom-0 -z-10 h-32 w-[26%]"
                    />
                    <KertasSobek
                        benih={101}
                        kasar={12}
                        className="bottom-0 left-[20%] -z-10 h-24 w-[58%] bg-[var(--dasar)]"
                    />
                </div>
            </section>

            {children}
        </div>
    );
}
