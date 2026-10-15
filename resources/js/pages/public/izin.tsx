import { Head, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    Loader2,
    Search,
    ShieldCheck,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import BrandLogo from '@/components/brand-logo';
import InputError from '@/components/input-error';
import {
    BuktiFoto,
    PilihanJenis,
    rentangTanggalIzin,
} from '@/components/izin/izin-fields';
import type { JenisIzin } from '@/components/izin/izin-fields';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { siswa as cariSiswaRoute, store, verifikasi } from '@/routes/izin';

type Sekolah = { id: string; name: string; city: string | null };
type Siswa = { id: string; nama: string; kelas: string | null };

type Props = {
    sekolah: Sekolah[];
    captcha: { soal: string };
    terverifikasi: boolean;
    maksHari: number;
};

const LANGKAH = ['Keamanan', 'Sekolah', 'Anak', 'Keterangan', 'Kirim'] as const;

const hariIni = () => new Date().toLocaleDateString('sv-SE');

function tokenCsrf(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

async function json<T>(
    url: string,
    init?: RequestInit,
): Promise<{ ok: boolean; status: number; data: T }> {
    const res = await fetch(url, {
        ...init,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': tokenCsrf(),
            'X-Requested-With': 'XMLHttpRequest',
            ...(init?.headers ?? {}),
        },
        credentials: 'same-origin',
    });

    return {
        ok: res.ok,
        status: res.status,
        data: (await res.json().catch(() => ({}))) as T,
    };
}

/**
 * Form izin/sakit publik, multi-langkah. Server memeriksa jawaban keamanan
 * sebelum pencarian siswa dan pengiriman dibuka.
 */
export default function IzinPublik({
    sekolah,
    captcha,
    terverifikasi,
    maksHari,
}: Props) {
    const [langkah, setLangkah] = useState(terverifikasi ? 1 : 0);
    const [soal, setSoal] = useState(captcha.soal);
    const [jawaban, setJawaban] = useState('');
    const [galatCaptcha, setGalatCaptcha] = useState<string | null>(null);
    const [memeriksa, setMemeriksa] = useState(false);

    const [cariSekolah, setCariSekolah] = useState('');
    const [sekolahDipilih, setSekolahDipilih] = useState<Sekolah | null>(null);

    const [cariAnak, setCariAnak] = useState('');
    const [hasil, setHasil] = useState<Siswa[] | null>(null);
    const [mencari, setMencari] = useState(false);
    const [galatCari, setGalatCari] = useState<string | null>(null);
    const [anak, setAnak] = useState<Siswa | null>(null);

    const form = useForm<{
        school_id: string;
        student_id: string;
        jenis: JenisIzin | '';
        tanggal_mulai: string;
        tanggal_selesai: string;
        alasan: string;
        bukti: File | null;
        pengirim_nama: string;
        pengirim_telepon: string;
        situs: string;
    }>({
        school_id: '',
        student_id: '',
        jenis: '',
        tanggal_mulai: hariIni(),
        tanggal_selesai: hariIni(),
        alasan: '',
        bukti: null,
        pengirim_nama: '',
        pengirim_telepon: '',
        situs: '',
    });

    const sekolahTersaring = useMemo(() => {
        const q = cariSekolah.trim().toLowerCase();

        return q
            ? sekolah.filter((s) =>
                  `${s.name} ${s.city ?? ''}`.toLowerCase().includes(q),
              )
            : sekolah;
    }, [cariSekolah, sekolah]);

    // Cari anak setelah 3 huruf, dengan jeda supaya tidak menembak tiap ketukan.
    useEffect(() => {
        if (langkah !== 2 || !sekolahDipilih || cariAnak.trim().length < 3) {
            return;
        }

        const timer = setTimeout(async () => {
            setMencari(true);
            setGalatCari(null);
            const url = cariSiswaRoute.url({
                query: { school_id: sekolahDipilih.id, q: cariAnak.trim() },
            });
            const res = await json<{ siswa?: Siswa[]; message?: string }>(url, {
                method: 'GET',
            });
            setMencari(false);

            if (res.status === 403) {
                setLangkah(0);
                setGalatCaptcha('Sesi keamanan habis. Jawab pertanyaan lagi.');

                return;
            }

            if (!res.ok) {
                setGalatCari(res.data.message ?? 'Pencarian gagal. Coba lagi.');

                return;
            }

            setHasil(res.data.siswa ?? []);
        }, 350);

        return () => clearTimeout(timer);
    }, [cariAnak, langkah, sekolahDipilih]);

    const kirimCaptcha = async (e: React.FormEvent) => {
        e.preventDefault();
        setMemeriksa(true);
        setGalatCaptcha(null);
        const res = await json<{ message?: string; soal?: string }>(
            verifikasi.url(),
            {
                method: 'POST',
                body: JSON.stringify({ jawaban: Number(jawaban) }),
            },
        );
        setMemeriksa(false);

        if (res.ok) {
            setLangkah(sekolahDipilih ? (anak ? 3 : 2) : 1);

            return;
        }

        setJawaban('');
        setGalatCaptcha(res.data.message ?? 'Jawaban belum tepat.');

        if (res.data.soal) {
            setSoal(res.data.soal);
        }
    };

    const pilihSekolah = (s: Sekolah) => {
        setSekolahDipilih(s);
        setAnak(null);
        setHasil(null);
        setCariAnak('');
        form.setData('school_id', s.id);
        setLangkah(2);
    };

    const pilihAnak = (s: Siswa) => {
        setAnak(s);
        form.setData('student_id', s.id);
        setLangkah(3);
    };

    const kirim = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(store.url(), {
            forceFormData: true,
            onError: (galat) => {
                if (galat.student_id) {
                    setLangkah(2);
                } else if (
                    galat.jenis ||
                    galat.tanggal_mulai ||
                    galat.tanggal_selesai ||
                    galat.alasan ||
                    galat.bukti
                ) {
                    setLangkah(3);
                }
            },
        });
    };

    const keteranganLengkap =
        form.data.jenis !== '' && form.data.alasan.trim().length >= 3;

    return (
        <>
            <Head title="Ajukan Izin Siswa" />
            <div className="min-h-dvh bg-[linear-gradient(180deg,#EAF3FB_0%,#F7FAFD_45%,#FFFFFF_100%)] dark:bg-[linear-gradient(180deg,#0B1626_0%,#0F1B2D_100%)]">
                <div className="mx-auto flex max-w-xl flex-col gap-6 px-4 py-8 sm:py-12">
                    <header className="flex items-center gap-3">
                        <BrandLogo className="size-9 rounded-lg" />
                        <div>
                            <h1 className="text-lg font-bold tracking-tight">
                                Ajukan izin / sakit
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Kabari sekolah bila anak tidak bisa hadir.
                            </p>
                        </div>
                    </header>

                    <ol
                        className="grid grid-cols-5 gap-1.5"
                        aria-label="Langkah"
                    >
                        {LANGKAH.map((nama, i) => (
                            <li key={nama} className="grid gap-1.5">
                                <span
                                    className={cn(
                                        'h-1.5 rounded-full',
                                        i <= langkah
                                            ? 'bg-primary'
                                            : 'bg-muted',
                                    )}
                                />
                                <span
                                    className={cn(
                                        'truncate text-[11px] font-medium',
                                        i === langkah
                                            ? 'text-foreground'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {nama}
                                </span>
                            </li>
                        ))}
                    </ol>

                    <section className="rounded-2xl border bg-card p-5 shadow-sm sm:p-6">
                        {langkah === 0 && (
                            <form
                                onSubmit={kirimCaptcha}
                                className="grid gap-4"
                            >
                                <div className="flex items-center gap-2 font-semibold">
                                    <ShieldCheck className="size-5 text-primary" />{' '}
                                    Pertanyaan keamanan
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    Jawab dulu supaya kami tahu Anda bukan
                                    robot.
                                </p>
                                <div className="grid gap-1.5">
                                    <Label
                                        htmlFor="jawaban"
                                        className="text-base"
                                    >
                                        Berapa {soal}?
                                    </Label>
                                    <Input
                                        id="jawaban"
                                        inputMode="numeric"
                                        autoFocus
                                        value={jawaban}
                                        onChange={(e) =>
                                            setJawaban(
                                                e.target.value.replace(
                                                    /\D/g,
                                                    '',
                                                ),
                                            )
                                        }
                                        className="h-11 text-lg"
                                    />
                                    <InputError
                                        message={galatCaptcha ?? undefined}
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={!jawaban || memeriksa}
                                    className="h-11"
                                >
                                    {memeriksa ? (
                                        <Loader2 className="size-4 animate-spin" />
                                    ) : (
                                        'Lanjut'
                                    )}
                                </Button>
                            </form>
                        )}

                        {langkah === 1 && (
                            <div className="grid gap-4">
                                <h2 className="font-semibold">Pilih sekolah</h2>
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        value={cariSekolah}
                                        onChange={(e) =>
                                            setCariSekolah(e.target.value)
                                        }
                                        placeholder="Cari nama sekolah"
                                        className="h-11 pl-9"
                                    />
                                </div>
                                <ul className="grid max-h-80 gap-2 overflow-y-auto">
                                    {sekolahTersaring.map((s) => (
                                        <li key={s.id}>
                                            <button
                                                type="button"
                                                onClick={() => pilihSekolah(s)}
                                                className={cn(
                                                    'flex w-full items-center justify-between rounded-xl border p-3 text-left hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                                    sekolahDipilih?.id ===
                                                        s.id &&
                                                        'border-primary bg-primary/5',
                                                )}
                                            >
                                                <span>
                                                    <span className="block font-medium">
                                                        {s.name}
                                                    </span>
                                                    {s.city && (
                                                        <span className="text-xs text-muted-foreground">
                                                            {s.city}
                                                        </span>
                                                    )}
                                                </span>
                                                <ArrowRight className="size-4 text-muted-foreground" />
                                            </button>
                                        </li>
                                    ))}
                                    {sekolahTersaring.length === 0 && (
                                        <li className="py-6 text-center text-sm text-muted-foreground">
                                            Sekolah tidak ditemukan.
                                        </li>
                                    )}
                                </ul>
                            </div>
                        )}

                        {langkah === 2 && sekolahDipilih && (
                            <div className="grid gap-4">
                                <div>
                                    <h2 className="font-semibold">
                                        Cari nama anak
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {sekolahDipilih.name}
                                    </p>
                                </div>
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        autoFocus
                                        value={cariAnak}
                                        onChange={(e) =>
                                            setCariAnak(e.target.value)
                                        }
                                        placeholder="Ketik minimal 3 huruf nama anak"
                                        className="h-11 pl-9"
                                    />
                                    {mencari && (
                                        <Loader2 className="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-muted-foreground" />
                                    )}
                                </div>
                                <InputError
                                    message={
                                        galatCari ?? form.errors.student_id
                                    }
                                />
                                {hasil !== null &&
                                    cariAnak.trim().length >= 3 && (
                                        <ul className="grid gap-2">
                                            {hasil.map((s) => (
                                                <li key={s.id}>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            pilihAnak(s)
                                                        }
                                                        className="flex w-full items-center justify-between rounded-xl border p-3 text-left hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                    >
                                                        <span>
                                                            <span className="block font-medium">
                                                                {s.nama}
                                                            </span>
                                                            <span className="text-xs text-muted-foreground">
                                                                Kelas{' '}
                                                                {s.kelas ?? '—'}
                                                            </span>
                                                        </span>
                                                        <ArrowRight className="size-4 text-muted-foreground" />
                                                    </button>
                                                </li>
                                            ))}
                                            {hasil.length === 0 && (
                                                <li className="py-4 text-center text-sm text-muted-foreground">
                                                    Nama tidak ditemukan.
                                                    Periksa ejaan atau sekolah
                                                    yang dipilih.
                                                </li>
                                            )}
                                        </ul>
                                    )}
                                <Button
                                    variant="outline"
                                    className="justify-self-start"
                                    onClick={() => setLangkah(1)}
                                >
                                    <ArrowLeft className="size-4" /> Ganti
                                    sekolah
                                </Button>
                            </div>
                        )}

                        {langkah === 3 && anak && (
                            <div className="grid gap-5">
                                <div className="rounded-xl bg-muted/60 p-3 text-sm">
                                    <span className="font-medium">
                                        {anak.nama}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {' '}
                                        · Kelas {anak.kelas ?? '—'}
                                    </span>
                                </div>
                                <div className="grid gap-2">
                                    <Label>Jenis</Label>
                                    <PilihanJenis
                                        nilai={form.data.jenis}
                                        onUbah={(j) => form.setData('jenis', j)}
                                    />
                                    <InputError message={form.errors.jenis} />
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="mulai">
                                            Dari tanggal
                                        </Label>
                                        <Input
                                            id="mulai"
                                            type="date"
                                            value={form.data.tanggal_mulai}
                                            onChange={(e) => {
                                                form.setData(
                                                    'tanggal_mulai',
                                                    e.target.value,
                                                );

                                                if (
                                                    form.data.tanggal_selesai <
                                                    e.target.value
                                                ) {
                                                    form.setData(
                                                        'tanggal_selesai',
                                                        e.target.value,
                                                    );
                                                }
                                            }}
                                        />
                                        <InputError
                                            message={form.errors.tanggal_mulai}
                                        />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="selesai">
                                            Sampai tanggal
                                        </Label>
                                        <Input
                                            id="selesai"
                                            type="date"
                                            min={form.data.tanggal_mulai}
                                            value={form.data.tanggal_selesai}
                                            onChange={(e) =>
                                                form.setData(
                                                    'tanggal_selesai',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                form.errors.tanggal_selesai
                                            }
                                        />
                                    </div>
                                    <p className="col-span-2 text-xs text-muted-foreground">
                                        Maksimal {maksHari} hari sekali ajukan.
                                    </p>
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="alasan">Alasan</Label>
                                    <Textarea
                                        id="alasan"
                                        rows={3}
                                        maxLength={500}
                                        value={form.data.alasan}
                                        onChange={(e) =>
                                            form.setData(
                                                'alasan',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Contoh: demam sejak semalam, berobat ke puskesmas."
                                    />
                                    <InputError message={form.errors.alasan} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label>
                                        Bukti foto{' '}
                                        <span className="font-normal text-muted-foreground">
                                            (surat dokter, dll. — opsional)
                                        </span>
                                    </Label>
                                    <BuktiFoto
                                        berkas={form.data.bukti}
                                        onUbah={(f) => form.setData('bukti', f)}
                                    />
                                    <InputError message={form.errors.bukti} />
                                </div>
                                <div className="flex justify-between">
                                    <Button
                                        variant="outline"
                                        onClick={() => setLangkah(2)}
                                    >
                                        <ArrowLeft className="size-4" /> Kembali
                                    </Button>
                                    <Button
                                        disabled={!keteranganLengkap}
                                        onClick={() => setLangkah(4)}
                                    >
                                        Lanjut <ArrowRight className="size-4" />
                                    </Button>
                                </div>
                            </div>
                        )}

                        {langkah === 4 && anak && sekolahDipilih && (
                            <form onSubmit={kirim} className="grid gap-5">
                                <h2 className="font-semibold">
                                    Data pengirim & periksa ulang
                                </h2>
                                {/* Jebakan bot: tidak terlihat dan tidak bisa difokus manusia. */}
                                <div
                                    aria-hidden="true"
                                    className="absolute -left-[9999px] h-0 w-0 overflow-hidden"
                                >
                                    <label htmlFor="situs">Situs web</label>
                                    <input
                                        id="situs"
                                        name="situs"
                                        tabIndex={-1}
                                        autoComplete="off"
                                        value={form.data.situs}
                                        onChange={(e) =>
                                            form.setData('situs', e.target.value)
                                        }
                                    />
                                </div>
                                <dl className="grid gap-2 rounded-xl bg-muted/60 p-4 text-sm">
                                    <div className="flex justify-between gap-3">
                                        <dt className="text-muted-foreground">
                                            Anak
                                        </dt>
                                        <dd className="text-right font-medium">
                                            {anak.nama}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-3">
                                        <dt className="text-muted-foreground">
                                            Sekolah
                                        </dt>
                                        <dd className="text-right">
                                            {sekolahDipilih.name}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-3">
                                        <dt className="text-muted-foreground">
                                            Jenis
                                        </dt>
                                        <dd>
                                            {form.data.jenis === 'SAKIT'
                                                ? 'Sakit'
                                                : 'Izin'}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-3">
                                        <dt className="text-muted-foreground">
                                            Tanggal
                                        </dt>
                                        <dd>
                                            {rentangTanggalIzin(
                                                form.data.tanggal_mulai,
                                                form.data.tanggal_selesai,
                                            )}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-3">
                                        <dt className="text-muted-foreground">
                                            Bukti
                                        </dt>
                                        <dd>
                                            {form.data.bukti
                                                ? 'Ada foto'
                                                : 'Tidak ada'}
                                        </dd>
                                    </div>
                                </dl>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="pengirim_nama">
                                        Nama Anda
                                    </Label>
                                    <Input
                                        id="pengirim_nama"
                                        value={form.data.pengirim_nama}
                                        onChange={(e) =>
                                            form.setData(
                                                'pengirim_nama',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Contoh: Ibu Sari (ibu)"
                                        autoComplete="name"
                                    />
                                    <InputError
                                        message={form.errors.pengirim_nama}
                                    />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="pengirim_telepon">
                                        Nomor WhatsApp
                                    </Label>
                                    <Input
                                        id="pengirim_telepon"
                                        inputMode="tel"
                                        value={form.data.pengirim_telepon}
                                        onChange={(e) =>
                                            form.setData(
                                                'pengirim_telepon',
                                                e.target.value.replace(
                                                    /[^\d+]/g,
                                                    '',
                                                ),
                                            )
                                        }
                                        placeholder="081234567890"
                                        autoComplete="tel"
                                    />
                                    <InputError
                                        message={form.errors.pengirim_telepon}
                                    />
                                </div>
                                <div className="flex justify-between">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setLangkah(3)}
                                    >
                                        <ArrowLeft className="size-4" /> Kembali
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={
                                            form.processing ||
                                            !form.data.pengirim_nama ||
                                            !form.data.pengirim_telepon
                                        }
                                    >
                                        {form.processing ? (
                                            <Loader2 className="size-4 animate-spin" />
                                        ) : (
                                            <Check className="size-4" />
                                        )}
                                        Kirim pengajuan
                                    </Button>
                                </div>
                            </form>
                        )}
                    </section>

                    <p className="text-center text-xs text-muted-foreground">
                        Sudah punya akun orang tua? Ajukan lewat menu{' '}
                        <span className="font-medium">Ajukan Izin</span> setelah
                        masuk.
                    </p>
                </div>
            </div>
        </>
    );
}
