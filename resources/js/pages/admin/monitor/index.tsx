import { Head, router, usePoll } from '@inertiajs/react';
import {
    AlertOctagon,
    CalendarOff,
    CheckCircle2,
    Clock,
    School,
} from 'lucide-react';
import { useState } from 'react';
import {
    index as monitorIndex,
    pantau,
} from '@/actions/App/Http/Controllers/Admin/MonitorController';
import { StatCard } from '@/components/dashboard/stat-card';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type Keadaan = 'tanpa_absen' | 'belum_mulai' | 'berjalan' | 'libur';

type Baris = {
    id: string;
    nama: string;
    dipantau: boolean;
    siswa: number;
    hadir: number;
    terlambat: number;
    izin_sakit: number;
    persen: number;
    scan_terakhir: string | null;
    absen_terakhir: string | null;
    hari_aktif: number;
    keadaan: Keadaan;
    peringkat?: number;
};

type Props = {
    monitor: {
        tanggal: string;
        rentang_hari: number;
        sekolah: Baris[];
        lainnya: Baris[];
        ringkasan: {
            total: number;
            tanpa_absen: number;
            berjalan: number;
            libur: number;
        };
    };
    hariIni: string;
};

const KEADAAN: Record<Keadaan, { label: string; kelas: string }> = {
    tanpa_absen: {
        label: 'Tidak ada absen',
        kelas: 'bg-red-600 text-white border-red-600',
    },
    belum_mulai: {
        label: 'Belum jam masuk',
        kelas: 'bg-muted text-muted-foreground',
    },
    berjalan: {
        label: 'Ada absen',
        kelas: 'bg-green-600/15 text-green-700 border-green-600/30 dark:text-green-400',
    },
    libur: {
        label: 'Libur / tanpa jadwal',
        kelas: 'bg-muted text-muted-foreground',
    },
};

const FORMAT_TANGGAL = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

function hariLalu(tanggal: string | null, acuan: string): string {
    if (!tanggal) {
        return 'belum pernah';
    }

    const selisih = Math.round(
        (new Date(acuan).getTime() - new Date(tanggal).getTime()) / 86_400_000,
    );

    return selisih <= 0 ? 'hari ini' : `${selisih} hari lalu`;
}

/**
 * Monitor super admin: sekolah yang dicentang "Pantau" diurutkan dari yang
 * paling jarang absen (atau belum pernah) di atas sampai yang paling sering.
 * Sekolah yang belum dicentang ada di bawah, tanpa peringkat.
 */
export default function MonitorSekolah({ monitor, hariIni }: Props) {
    const lihatHariIni = monitor.tanggal === hariIni;
    const [menyimpan, setMenyimpan] = useState<string | null>(null);

    // Hanya hari ini yang perlu disegarkan; tanggal lampau tidak berubah.
    usePoll(60_000, { only: ['monitor'] }, { autoStart: lihatHariIni });

    function gantiTanggal(tanggal: string) {
        router.get(
            monitorIndex.url({ query: { tanggal } }),
            {},
            { preserveScroll: true },
        );
    }

    function aturPantau(id: string, dipantau: boolean) {
        router.post(
            pantau.url(id),
            { dipantau },
            {
                preserveScroll: true,
                only: ['monitor', 'flash'],
                onStart: () => setMenyimpan(id),
                onFinish: () => setMenyimpan(null),
            },
        );
    }

    return (
        <>
            <Head title="Monitor Sekolah" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Monitor Sekolah
                        </h1>
                        <p className="text-sm text-muted-foreground capitalize">
                            {FORMAT_TANGGAL.format(new Date(monitor.tanggal))}
                            {lihatHariIni && ' · diperbarui tiap 1 menit'}
                        </p>
                    </div>
                    <Input
                        type="date"
                        className="sm:w-48"
                        value={monitor.tanggal}
                        max={hariIni}
                        onChange={(e) =>
                            e.target.value && gantiTanggal(e.target.value)
                        }
                    />
                </div>

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatCard
                        title="Tidak Ada Absen"
                        value={monitor.ringkasan.tanpa_absen}
                        hint="Ada jadwal, 0 absen"
                        icon={<AlertOctagon />}
                        color="red"
                    />
                    <StatCard
                        title="Ada Absen"
                        value={monitor.ringkasan.berjalan}
                        hint="Sudah ada siswa absen"
                        icon={<CheckCircle2 />}
                        color="green"
                    />
                    <StatCard
                        title="Libur / Tanpa Jadwal"
                        value={monitor.ringkasan.libur}
                        hint="Tidak ada jadwal hari ini"
                        icon={<CalendarOff />}
                        color="amber"
                    />
                    <StatCard
                        title="Sekolah Dipantau"
                        value={monitor.ringkasan.total}
                        hint="Centang di daftar bawah"
                        icon={<School />}
                        color="blue"
                    />
                </div>

                <section className="flex flex-col gap-3">
                    <div>
                        <h2 className="font-semibold">
                            Peringkat sekolah dipantau
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Paling atas: paling jarang absen dalam{' '}
                            {monitor.rentang_hari} hari terakhir.
                        </p>
                    </div>
                    {monitor.sekolah.length === 0 ? (
                        <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                            Belum ada sekolah yang dipantau. Centang sekolah
                            yang memakai absensi di daftar &ldquo;Sekolah
                            lain&rdquo; di bawah.
                        </p>
                    ) : (
                        <TabelSekolah
                            baris={monitor.sekolah}
                            tanggal={monitor.tanggal}
                            rentang={monitor.rentang_hari}
                            menyimpan={menyimpan}
                            onPantau={aturPantau}
                            berperingkat
                        />
                    )}
                </section>

                <section className="flex flex-col gap-3">
                    <div>
                        <h2 className="font-semibold">Sekolah lain</h2>
                        <p className="text-sm text-muted-foreground">
                            Tidak ikut peringkat. Centang sekolah yang sudah
                            memakai absensi supaya ikut dipantau.
                        </p>
                    </div>
                    <TabelSekolah
                        baris={monitor.lainnya}
                        tanggal={monitor.tanggal}
                        rentang={monitor.rentang_hari}
                        menyimpan={menyimpan}
                        onPantau={aturPantau}
                    />
                </section>
            </div>
        </>
    );
}

function TabelSekolah({
    baris,
    tanggal,
    rentang,
    menyimpan,
    onPantau,
    berperingkat = false,
}: {
    baris: Baris[];
    tanggal: string;
    rentang: number;
    menyimpan: string | null;
    onPantau: (id: string, dipantau: boolean) => void;
    berperingkat?: boolean;
}) {
    if (baris.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Semua sekolah aktif sudah dipantau.
            </p>
        );
    }

    return (
        <div className="overflow-x-auto rounded-xl border bg-card">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b bg-muted/50 text-left text-xs text-muted-foreground">
                        <th className="w-12 px-3 py-2 text-center">Pantau</th>
                        {berperingkat && <th className="w-10 px-2 py-2">#</th>}
                        <th className="px-3 py-2">Sekolah</th>
                        <th className="px-3 py-2">Hari ini</th>
                        <th className="px-3 py-2 text-right">Hadir</th>
                        <th className="px-3 py-2 text-right">Izin/Sakit</th>
                        <th className="px-3 py-2 text-right">% Hadir</th>
                        <th className="px-3 py-2">Scan terakhir</th>
                        <th className="px-3 py-2">Absen terakhir</th>
                        <th className="px-3 py-2">
                            Absen {rentang} hari terakhir
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {baris.map((b) => (
                        <tr
                            key={b.id}
                            className={cn(
                                'border-b last:border-0',
                                berperingkat &&
                                    b.keadaan === 'tanpa_absen' &&
                                    'bg-red-50/60 dark:bg-red-950/20',
                            )}
                        >
                            <td className="px-3 py-2 text-center">
                                <Checkbox
                                    checked={b.dipantau}
                                    disabled={menyimpan === b.id}
                                    aria-label={`Pantau ${b.nama}`}
                                    onCheckedChange={(v) =>
                                        onPantau(b.id, v === true)
                                    }
                                />
                            </td>
                            {berperingkat && (
                                <td className="px-2 py-2 font-semibold tabular-nums">
                                    {b.peringkat}
                                </td>
                            )}
                            <td className="px-3 py-2">
                                <p className="font-medium">{b.nama}</p>
                                <p className="text-xs text-muted-foreground tabular-nums">
                                    {b.siswa} siswa aktif
                                </p>
                            </td>
                            <td className="px-3 py-2">
                                <Badge
                                    variant="outline"
                                    className={KEADAAN[b.keadaan].kelas}
                                >
                                    {KEADAAN[b.keadaan].label}
                                </Badge>
                            </td>
                            <td className="px-3 py-2 text-right tabular-nums">
                                {b.hadir}
                                {b.terlambat > 0 && (
                                    <span className="ml-1 text-xs text-muted-foreground">
                                        ({b.terlambat} telat)
                                    </span>
                                )}
                            </td>
                            <td className="px-3 py-2 text-right tabular-nums">
                                {b.izin_sakit}
                            </td>
                            <td className="px-3 py-2 text-right font-medium tabular-nums">
                                {b.persen}%
                            </td>
                            <td className="px-3 py-2 tabular-nums">
                                {b.scan_terakhir ? (
                                    <span className="inline-flex items-center gap-1">
                                        <Clock className="size-3.5 text-muted-foreground" />
                                        {b.scan_terakhir}
                                    </span>
                                ) : (
                                    '-'
                                )}
                            </td>
                            <td className="px-3 py-2 text-muted-foreground">
                                {hariLalu(b.absen_terakhir, tanggal)}
                            </td>
                            <td className="px-3 py-2">
                                <div className="flex items-center gap-2">
                                    <div className="h-1.5 w-20 overflow-hidden rounded-full bg-muted">
                                        <div
                                            className={cn(
                                                'h-full rounded-full',
                                                b.hari_aktif === 0
                                                    ? 'bg-red-500'
                                                    : b.hari_aktif < rentang / 4
                                                      ? 'bg-amber-500'
                                                      : 'bg-green-600',
                                            )}
                                            style={{
                                                width: `${Math.min(100, (b.hari_aktif / rentang) * 100)}%`,
                                            }}
                                        />
                                    </div>
                                    <span className="text-xs tabular-nums">
                                        {b.hari_aktif === 0
                                            ? 'tidak pernah'
                                            : `${b.hari_aktif} hari`}
                                    </span>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

MonitorSekolah.layout = {
    breadcrumbs: [{ title: 'Monitor Sekolah', href: '/admin/monitor' }],
};
