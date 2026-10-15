import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { dashboard as beranda } from '@/routes/penyelenggara';
import { index as jadwalIndex } from '@/routes/penyelenggara/jadwal';
import { index as kehadiranIndex } from '@/routes/penyelenggara/kehadiran';
import { index as laporanIndex } from '@/routes/penyelenggara/laporan';
import { index as pesertaIndex } from '@/routes/penyelenggara/peserta';
import { dashboard, show as programShow } from '@/routes/penyelenggara/program';
import { WARNA_STATUS } from './kehadiran';

type Ringkasan = {
    sesi: { id: string; name: string; datang: string };
    target: number;
    counts: Record<string, number>;
    belum: { id: string; nama: string; grups: string }[];
};

type Props = {
    program: { id: string; name: string; tanggal: string };
    adaSesiHariIni: boolean;
    libur: boolean;
    akses: {
        program: boolean;
        kehadiran: boolean;
        peserta: boolean;
        laporan: boolean;
    };
    grups: { id: string; name: string }[];
    filters: { grup: string; bulan: number };
    peringkatTersedia: boolean;
    ringkasan?: Ringkasan[];
    peringkat?: {
        nama: string;
        rata_menit: number;
        rata_jam: string | null;
        hari: number;
    }[];
    tren?: { minggu: string; persen: number; total: number }[];
};

const LABEL: Record<string, string> = {
    HADIR: 'Hadir',
    TERLAMBAT: 'Terlambat',
    IZIN: 'Izin',
    SAKIT: 'Sakit',
    ALPA: 'Alpa',
    BELUM: 'Belum hadir',
};

export default function ProgramDashboard({
    program,
    adaSesiHariIni,
    libur,
    akses,
    grups,
    filters,
    peringkatTersedia,
    ringkasan,
    peringkat,
    tren,
}: Props) {
    const filter = (patch: Partial<Props['filters']>) =>
        router.get(
            dashboard.url(program.id),
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true },
        );

    return (
        <>
            <Head title={`Dashboard — ${program.name}`} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Dashboard
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {akses.program ? (
                                <Link
                                    href={programShow(program.id)}
                                    className="underline"
                                >
                                    {program.name}
                                </Link>
                            ) : (
                                program.name
                            )}{' '}
                            ·{' '}
                            {new Intl.DateTimeFormat('id-ID', {
                                dateStyle: 'full',
                            }).format(new Date(`${program.tanggal}T00:00:00`))}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <select
                            aria-label="Grup"
                            className="h-9 rounded-md border bg-background px-2 text-sm"
                            value={filters.grup}
                            onChange={(e) => filter({ grup: e.target.value })}
                        >
                            <option value="">Semua grup</option>
                            {grups.map((g) => (
                                <option key={g.id} value={g.id}>
                                    {g.name}
                                </option>
                            ))}
                        </select>
                        {akses.peserta && (
                            <Link
                                href={pesertaIndex(program.id)}
                                className="inline-flex h-9 items-center rounded-md border px-3 text-sm"
                            >
                                Peserta
                            </Link>
                        )}
                        {akses.kehadiran && (
                            <Link
                                href={kehadiranIndex(program.id)}
                                className="inline-flex h-9 items-center rounded-md border px-3 text-sm"
                            >
                                Kehadiran
                            </Link>
                        )}
                        {akses.laporan && (
                            <Link
                                href={laporanIndex(program.id)}
                                className="inline-flex h-9 items-center rounded-md border px-3 text-sm"
                            >
                                Laporan
                            </Link>
                        )}
                    </div>
                </div>

                {!adaSesiHariIni ? (
                    <Card>
                        <CardContent className="py-8 text-center text-sm text-muted-foreground">
                            {libur ? (
                                'Hari ini libur, tidak ada sesi.'
                            ) : akses.program ? (
                                <>
                                    Tidak ada sesi hari ini. Atur jadwal di{' '}
                                    <Link
                                        href={jadwalIndex(program.id)}
                                        className="underline"
                                    >
                                        halaman Jadwal
                                    </Link>
                                    .
                                </>
                            ) : (
                                'Tidak ada sesi hari ini.'
                            )}
                        </CardContent>
                    </Card>
                ) : ringkasan === undefined ? (
                    <div className="grid gap-4 md:grid-cols-2">
                        <Skeleton className="h-40" />
                        <Skeleton className="h-40" />
                    </div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {ringkasan.map((r) => (
                            <Card key={r.sesi.id}>
                                <CardHeader>
                                    <CardTitle className="flex items-baseline justify-between text-base">
                                        <span>{r.sesi.name}</span>
                                        <span className="text-sm font-normal text-muted-foreground">
                                            {r.sesi.datang}
                                        </span>
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-3">
                                    <div className="text-3xl font-semibold tabular-nums">
                                        {r.counts.HADIR + r.counts.TERLAMBAT}
                                        <span className="text-base font-normal text-muted-foreground">
                                            /{r.target} hadir
                                        </span>
                                    </div>
                                    <div className="flex flex-wrap gap-1">
                                        {Object.entries(r.counts)
                                            .filter(([, n]) => n > 0)
                                            .map(([s, n]) => (
                                                <Badge
                                                    key={s}
                                                    variant={
                                                        WARNA_STATUS[s] ??
                                                        'outline'
                                                    }
                                                >
                                                    {LABEL[s] ?? s}: {n}
                                                </Badge>
                                            ))}
                                    </div>
                                    {r.belum.length > 0 && (
                                        <details className="text-sm">
                                            <summary className="cursor-pointer text-muted-foreground">
                                                Belum hadir ({r.belum.length})
                                            </summary>
                                            <ul className="mt-2 grid gap-1">
                                                {r.belum.map((b) => (
                                                    <li key={b.id}>
                                                        {b.nama}{' '}
                                                        <span className="text-xs text-muted-foreground">
                                                            {b.grups}
                                                        </span>
                                                    </li>
                                                ))}
                                            </ul>
                                        </details>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {peringkatTersedia && (
                    <div className="grid gap-4 md:grid-cols-2">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle className="text-base">
                                    Tercepat hadir
                                </CardTitle>
                                <select
                                    aria-label="Rentang"
                                    className="h-8 rounded-md border bg-background px-2 text-sm"
                                    value={filters.bulan}
                                    onChange={(e) =>
                                        filter({
                                            bulan: Number(e.target.value),
                                        })
                                    }
                                >
                                    <option value={1}>1 bulan</option>
                                    <option value={2}>2 bulan</option>
                                    <option value={3}>3 bulan</option>
                                </select>
                            </CardHeader>
                            <CardContent>
                                {peringkat === undefined ? (
                                    <Skeleton className="h-32" />
                                ) : peringkat.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Belum cukup data (minimal 3 kali hadir).
                                    </p>
                                ) : (
                                    <ol className="grid gap-1 text-sm">
                                        {peringkat.map((p, i) => (
                                            <li
                                                key={p.nama + i}
                                                className="flex justify-between"
                                            >
                                                <span>
                                                    {i + 1}. {p.nama}
                                                </span>
                                                <span className="text-muted-foreground tabular-nums">
                                                    datang {p.rata_jam ?? '–'} ·{' '}
                                                    {p.rata_menit > 0
                                                        ? '+'
                                                        : ''}
                                                    {p.rata_menit} menit dari
                                                    mulai · {p.hari}×
                                                </span>
                                            </li>
                                        ))}
                                    </ol>
                                )}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Tren kehadiran per minggu
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {tren === undefined ? (
                                    <Skeleton className="h-32" />
                                ) : (
                                    <ul className="grid gap-2 text-sm">
                                        {tren.map((t) => (
                                            <li
                                                key={t.minggu}
                                                className="grid grid-cols-[6rem_1fr_3rem] items-center gap-2"
                                            >
                                                <span className="text-muted-foreground">
                                                    {new Intl.DateTimeFormat(
                                                        'id-ID',
                                                        {
                                                            day: 'numeric',
                                                            month: 'short',
                                                        },
                                                    ).format(
                                                        new Date(
                                                            `${t.minggu}T00:00:00`,
                                                        ),
                                                    )}
                                                </span>
                                                <span className="h-2 rounded bg-muted">
                                                    <span
                                                        className="block h-2 rounded bg-primary"
                                                        style={{
                                                            width: `${t.persen}%`,
                                                        }}
                                                    />
                                                </span>
                                                <span className="text-right tabular-nums">
                                                    {t.persen}%
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </>
    );
}

ProgramDashboard.layout = {
    breadcrumbs: [{ title: 'Beranda', href: beranda() }],
};
