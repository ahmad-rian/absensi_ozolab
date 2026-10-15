import { Head, Link } from '@inertiajs/react';
import {
    BookOpen,
    Building2,
    CalendarCheck,
    CalendarRange,
    ClipboardCheck,
    HardDrive,
    Plus,
    UserCog,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { MasukPenyelenggara } from '@/components/general/masuk-penyelenggara';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard, pengaturan, pengguna, program } from '@/routes/general';
import { index as penyelenggaraIndex } from '@/routes/general/penyelenggara';

type Props = {
    tanggal: string;
    statistik: {
        penyelenggara_total: number;
        penyelenggara_aktif: number;
        program_berjalan: number;
        peserta_aktif: number;
        akun: number;
        sesi_hari_ini: number;
        hadir_hari_ini: number;
        tercatat_hari_ini: number;
    };
    hajiLama: {
        layout: number;
        peserta: number;
        kartu_jadi: number;
        hadir_hari_ini: number;
    };
    driveSiap: boolean;
    penyelenggaras: {
        id: string;
        name: string;
        is_active: boolean;
        program: number;
        peserta: number;
        kuota: number | null;
        akun: number;
    }[];
};

const angka = (n: number) => new Intl.NumberFormat('id-ID').format(n);

function Statistik({
    icon: Icon,
    label,
    value,
    detail,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
    detail?: string;
}) {
    return (
        <div className="flex items-start gap-3 rounded-xl border bg-card p-4">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <Icon className="size-4" />
            </span>
            <div className="min-w-0">
                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </p>
                <p className="mt-1 text-2xl font-bold tabular-nums">{value}</p>
                {detail && (
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {detail}
                    </p>
                )}
            </div>
        </div>
    );
}

function Pintasan({
    href,
    icon: Icon,
    judul,
    keterangan,
}: {
    href: string;
    icon: LucideIcon;
    judul: string;
    keterangan: string;
}) {
    return (
        <Link
            href={href}
            className="group flex items-center gap-3 rounded-xl border bg-card p-4 transition-colors hover:border-primary/40 hover:bg-primary/5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground group-hover:bg-primary/10 group-hover:text-primary">
                <Icon className="size-4" />
            </span>
            <span className="min-w-0">
                <span className="block text-sm font-semibold">{judul}</span>
                <span className="block truncate text-xs text-muted-foreground">
                    {keterangan}
                </span>
            </span>
        </Link>
    );
}

/** Ringkasan workspace Absensi General: lintas penyelenggara + haji lama. */
export default function GeneralDashboard({
    tanggal,
    statistik,
    hajiLama,
    driveSiap,
    penyelenggaras,
}: Props) {
    const persenHadir =
        statistik.tercatat_hari_ini > 0
            ? Math.round(
                  (statistik.hadir_hari_ini / statistik.tercatat_hari_ini) *
                      100,
              )
            : null;

    return (
        <>
            <Head title="Absensi General" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <p className="text-sm font-semibold text-primary">
                            Absensi General
                        </p>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Ringkasan
                        </h1>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {new Intl.DateTimeFormat('id-ID', {
                            dateStyle: 'full',
                        }).format(new Date(`${tanggal}T00:00:00`))}
                    </p>
                </div>

                {!driveSiap && (
                    <p className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                        Folder Google Drive kartu peserta belum diatur.{' '}
                        <Link
                            href={pengaturan()}
                            className="font-medium underline"
                        >
                            Atur di Pengaturan
                        </Link>
                    </p>
                )}

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Statistik
                        icon={Building2}
                        label="Penyelenggara aktif"
                        value={angka(statistik.penyelenggara_aktif)}
                        detail={`dari ${angka(statistik.penyelenggara_total)} penyelenggara`}
                    />
                    <Statistik
                        icon={CalendarRange}
                        label="Program berjalan"
                        value={angka(statistik.program_berjalan)}
                    />
                    <Statistik
                        icon={Users}
                        label="Peserta aktif"
                        value={angka(statistik.peserta_aktif)}
                        detail={`${angka(statistik.akun)} akun penyelenggara`}
                    />
                    <Statistik
                        icon={CalendarCheck}
                        label="Kehadiran hari ini"
                        value={persenHadir === null ? '–' : `${persenHadir}%`}
                        detail={`${angka(statistik.hadir_hari_ini)} hadir · ${angka(statistik.sesi_hari_ini)} sesi`}
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle className="text-base">
                                Penyelenggara
                            </CardTitle>
                            <Link
                                href={penyelenggaraIndex()}
                                className="text-sm font-medium text-primary hover:underline"
                            >
                                Kelola semua
                            </Link>
                        </CardHeader>
                        <CardContent>
                            {penyelenggaras.length === 0 ? (
                                <p className="py-6 text-center text-sm text-muted-foreground">
                                    Belum ada penyelenggara.{' '}
                                    <Link
                                        href={penyelenggaraIndex()}
                                        className="font-medium text-primary underline"
                                    >
                                        Tambahkan yang pertama
                                    </Link>
                                </p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="text-left text-xs text-muted-foreground">
                                            <tr>
                                                <th className="pb-2 font-medium">
                                                    Nama
                                                </th>
                                                <th className="pb-2 text-right font-medium">
                                                    Program
                                                </th>
                                                <th className="pb-2 font-medium">
                                                    Peserta / kuota
                                                </th>
                                                <th className="pb-2 text-right font-medium">
                                                    Akun
                                                </th>
                                                <th className="pb-2" />
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {penyelenggaras.map((p) => {
                                                const persen = p.kuota
                                                    ? Math.min(
                                                          100,
                                                          Math.round(
                                                              (p.peserta /
                                                                  p.kuota) *
                                                                  100,
                                                          ),
                                                      )
                                                    : null;

                                                return (
                                                    <tr
                                                        key={p.id}
                                                        className="border-t"
                                                    >
                                                        <td className="py-2.5 pr-3">
                                                            <span className="font-medium">
                                                                {p.name}
                                                            </span>
                                                            {!p.is_active && (
                                                                <Badge
                                                                    variant="secondary"
                                                                    className="ml-2"
                                                                >
                                                                    Nonaktif
                                                                </Badge>
                                                            )}
                                                        </td>
                                                        <td className="py-2.5 text-right tabular-nums">
                                                            {p.program}
                                                        </td>
                                                        <td className="w-44 py-2.5 pl-4">
                                                            <span className="text-xs tabular-nums">
                                                                {angka(
                                                                    p.peserta,
                                                                )}
                                                                {p.kuota
                                                                    ? ` / ${angka(p.kuota)}`
                                                                    : ' · tanpa batas'}
                                                            </span>
                                                            {persen !==
                                                                null && (
                                                                <span className="mt-1 block h-1.5 overflow-hidden rounded-full bg-muted">
                                                                    <span
                                                                        className={
                                                                            persen >=
                                                                            90
                                                                                ? 'block h-full bg-amber-500'
                                                                                : 'block h-full bg-primary'
                                                                        }
                                                                        style={{
                                                                            width: `${persen}%`,
                                                                        }}
                                                                    />
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="py-2.5 text-right tabular-nums">
                                                            {p.akun}
                                                        </td>
                                                        <td className="py-2.5 pl-3 text-right">
                                                            <MasukPenyelenggara
                                                                penyelenggaraId={
                                                                    p.id
                                                                }
                                                            />
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <BookOpen className="size-4 text-primary" />
                                Haji (kartu lama)
                            </CardTitle>
                            <Link
                                href="/kartu-bebas"
                                className="text-sm font-medium text-primary hover:underline"
                            >
                                Buka
                            </Link>
                        </CardHeader>
                        <CardContent className="grid gap-3 text-sm">
                            <dl className="grid grid-cols-2 gap-3">
                                <div className="rounded-lg bg-muted/60 p-3">
                                    <dt className="text-xs text-muted-foreground">
                                        Peserta
                                    </dt>
                                    <dd className="text-xl font-bold tabular-nums">
                                        {angka(hajiLama.peserta)}
                                    </dd>
                                </div>
                                <div className="rounded-lg bg-muted/60 p-3">
                                    <dt className="text-xs text-muted-foreground">
                                        Kartu jadi
                                    </dt>
                                    <dd className="text-xl font-bold tabular-nums">
                                        {angka(hajiLama.kartu_jadi)}
                                    </dd>
                                </div>
                                <div className="rounded-lg bg-muted/60 p-3">
                                    <dt className="text-xs text-muted-foreground">
                                        Layout
                                    </dt>
                                    <dd className="text-xl font-bold tabular-nums">
                                        {angka(hajiLama.layout)}
                                    </dd>
                                </div>
                                <div className="rounded-lg bg-muted/60 p-3">
                                    <dt className="text-xs text-muted-foreground">
                                        Hadir hari ini
                                    </dt>
                                    <dd className="text-xl font-bold tabular-nums">
                                        {angka(hajiLama.hadir_hari_ini)}
                                    </dd>
                                </div>
                            </dl>
                            <p className="text-xs text-muted-foreground">
                                Kartu yang sudah tercetak, link scan, dan
                                riwayat absensinya tetap berjalan seperti
                                sebelumnya.
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div>
                    <h2 className="mb-3 text-sm font-semibold text-muted-foreground">
                        Pintasan
                    </h2>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <Pintasan
                            href={penyelenggaraIndex.url()}
                            icon={Plus}
                            judul="Tambah penyelenggara"
                            keterangan="Biro, panitia, kantor, atau instansi baru"
                        />
                        <Pintasan
                            href={program.url()}
                            icon={CalendarRange}
                            judul="Semua program"
                            keterangan="Program berjalan lintas penyelenggara"
                        />
                        <Pintasan
                            href={pengguna.url()}
                            icon={UserCog}
                            judul="Pengguna"
                            keterangan="Admin & pendamping semua penyelenggara"
                        />
                        <Pintasan
                            href="/kartu-bebas/absensi"
                            icon={ClipboardCheck}
                            judul="Link scan haji lama"
                            keterangan="Absensi & link scan kartu tercetak"
                        />
                        <Pintasan
                            href={pengaturan.url()}
                            icon={HardDrive}
                            judul="Pengaturan"
                            keterangan="Branding, Google Drive, WhatsApp"
                        />
                    </div>
                </div>
            </div>
        </>
    );
}

GeneralDashboard.layout = {
    breadcrumbs: [{ title: 'Absensi General', href: dashboard() }],
};
