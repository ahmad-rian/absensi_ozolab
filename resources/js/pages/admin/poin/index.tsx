import { Head, Link, router } from '@inertiajs/react';
import { History, Plus, Search, Settings2 } from 'lucide-react';
import { useState } from 'react';
import { CatatPelanggaran } from '@/components/poin/catat-pelanggaran';
import type { KategoriPoin } from '@/components/poin/catat-pelanggaran';
import { GAYA_LEVEL, LevelPill } from '@/components/poin/level-poin';
import type { AmbangLevel, LevelPoin } from '@/components/poin/level-poin';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index, pengaturan, riwayat } from '@/routes/admin/poin';
import { show as showSiswa } from '@/routes/admin/siswa';

type Baris = {
    id: string;
    nama: string;
    nis: string;
    kelas: string | null;
    foto: string | null;
    total: number;
    terlambat: number;
    alpa: number;
    manual: number;
    level: LevelPoin;
};

type Props = {
    siswa: {
        data: Baris[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    ringkasan: { kuning: number; orange: number; merah: number; siswa: number };
    setup: {
        terlambat: number;
        alpa: number;
        kategori: KategoriPoin[];
        level: AmbangLevel;
        semester_mulai: string;
    };
    classrooms: { id: string; name: string }[];
    filters: { kelas: string; q: string; level: string };
    bolehKelola: boolean;
};

const URUTAN_LEVEL = ['merah', 'orange', 'kuning'] as const;

/** Rekap poin pelanggaran per siswa untuk semester berjalan. */
export default function PoinIndex({
    siswa,
    ringkasan,
    setup,
    classrooms,
    filters,
    bolehKelola,
}: Props) {
    const [catat, setCatat] = useState(false);

    const muat = (patch: Partial<Props['filters']>) =>
        router.get(
            index.url(),
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Poin Siswa" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Poin Siswa
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Terlambat +{setup.terlambat}, tidak hadir +
                            {setup.alpa}, dicatat otomatis tiap jam. Pelanggaran
                            lain dicatat guru BK.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={riwayat()}>
                                <History className="size-4" /> Riwayat
                            </Link>
                        </Button>
                        {bolehKelola && (
                            <>
                                <Button variant="outline" asChild>
                                    <Link href={pengaturan()}>
                                        <Settings2 className="size-4" /> Atur
                                        poin
                                    </Link>
                                </Button>
                                <Button onClick={() => setCatat(true)}>
                                    <Plus className="size-4" /> Catat
                                    pelanggaran
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <button
                        type="button"
                        onClick={() => muat({ level: '' })}
                        className={cn(
                            'rounded-xl border bg-card p-4 text-left transition-colors hover:bg-muted/50',
                            filters.level === '' && 'ring-2 ring-primary',
                        )}
                    >
                        <p className="text-xs font-medium text-muted-foreground">
                            Siswa berpoin
                        </p>
                        <p className="mt-1 text-2xl font-bold tabular-nums">
                            {ringkasan.siswa}
                        </p>
                    </button>
                    {URUTAN_LEVEL.map((l) => (
                        <button
                            key={l}
                            type="button"
                            onClick={() =>
                                muat({ level: filters.level === l ? '' : l })
                            }
                            className={cn(
                                'rounded-xl border bg-card p-4 text-left transition-colors hover:bg-muted/50',
                                filters.level === l && 'ring-2 ring-primary',
                            )}
                        >
                            <p className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                <span
                                    className={cn(
                                        'size-2 rounded-full',
                                        GAYA_LEVEL[l].titik,
                                    )}
                                />
                                {GAYA_LEVEL[l].label} · ≥ {setup.level[l]}
                            </p>
                            <p className="mt-1 text-2xl font-bold tabular-nums">
                                {ringkasan[l]}
                            </p>
                        </button>
                    ))}
                </div>

                <div className="flex flex-col gap-2 sm:flex-row">
                    <select
                        aria-label="Kelas"
                        className="h-9 rounded-md border bg-background px-2 text-sm sm:w-48"
                        value={filters.kelas}
                        onChange={(e) => muat({ kelas: e.target.value })}
                    >
                        <option value="">Semua kelas</option>
                        {classrooms.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label="Level"
                        className="h-9 rounded-md border bg-background px-2 text-sm sm:w-40"
                        value={filters.level}
                        onChange={(e) => muat({ level: e.target.value })}
                    >
                        <option value="">Semua level</option>
                        <option value="merah">Merah</option>
                        <option value="orange">Orange</option>
                        <option value="kuning">Kuning</option>
                        <option value="aman">Aman</option>
                    </select>
                    <div className="relative sm:max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            className="pl-9"
                            placeholder="Cari nama siswa…"
                            defaultValue={filters.q}
                            onKeyDown={(e) =>
                                e.key === 'Enter' &&
                                muat({ q: e.currentTarget.value })
                            }
                        />
                    </div>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th className="px-4 py-3 font-medium">Siswa</th>
                                <th className="px-4 py-3 font-medium">Kelas</th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Terlambat
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Tidak hadir
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Pelanggaran
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Total
                                </th>
                                <th className="px-4 py-3 font-medium">Level</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {siswa.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Belum ada siswa berpoin di semester ini.
                                    </td>
                                </tr>
                            )}
                            {siswa.data.map((s) => (
                                <tr key={s.id} className="hover:bg-muted/30">
                                    <td className="px-4 py-3">
                                        <Link
                                            href={showSiswa.url(s.id, {
                                                query: { tab: 'poin' },
                                            })}
                                            className="flex items-center gap-3 hover:underline"
                                        >
                                            {s.foto ? (
                                                <img
                                                    src={s.foto}
                                                    alt=""
                                                    className="size-8 rounded-full object-cover"
                                                />
                                            ) : (
                                                <span className="flex size-8 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                                                    {s.nama.slice(0, 1)}
                                                </span>
                                            )}
                                            <span>
                                                <span className="block font-medium">
                                                    {s.nama}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {s.nis}
                                                </span>
                                            </span>
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">
                                        {s.kelas ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {s.terlambat}×
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {s.alpa}×
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {s.manual}×
                                    </td>
                                    <td className="px-4 py-3 text-right text-base font-bold tabular-nums">
                                        {s.total}
                                    </td>
                                    <td className="px-4 py-3">
                                        <LevelPill level={s.level} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span>{siswa.total} siswa</span>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!siswa.prev_page_url}
                            onClick={() =>
                                siswa.prev_page_url &&
                                router.get(siswa.prev_page_url)
                            }
                        >
                            Sebelumnya
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!siswa.next_page_url}
                            onClick={() =>
                                siswa.next_page_url &&
                                router.get(siswa.next_page_url)
                            }
                        >
                            Berikutnya
                        </Button>
                    </div>
                </div>
            </div>

            {bolehKelola && (
                <CatatPelanggaran
                    open={catat}
                    onOpenChange={setCatat}
                    kategori={setup.kategori}
                />
            )}
        </>
    );
}

PoinIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Poin Siswa', href: index() },
    ],
};
