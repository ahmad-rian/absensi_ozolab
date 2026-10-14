import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes/penyelenggara';
import { index, rotate, update } from '@/routes/penyelenggara/kehadiran';
import { show as programShow } from '@/routes/penyelenggara/program';

type Baris = {
    id: string;
    nama: string;
    grups: string[];
    status: string;
    datang: string | null;
    pulang: string | null;
    sumber: string | null;
    catatan: string | null;
};

type Props = {
    program: { id: string; name: string; archived: boolean };
    tanggal: string;
    sesis: { id: string; name: string; datang: string }[];
    sesi: { id: string; name: string } | null;
    peserta: Baris[];
    riwayat: {
        waktu: string;
        oleh: string;
        peserta: string | null;
        lama: string | null;
        baru: string | null;
    }[];
    grups: { id: string; name: string }[];
    statusOptions: { value: string; label: string }[];
    linkScan: { kamera: string; ringan: string } | null;
    filters: { grup: string; status: string };
};

export const WARNA_STATUS: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    HADIR: 'default',
    TERLAMBAT: 'secondary',
    IZIN: 'outline',
    SAKIT: 'outline',
    ALPA: 'destructive',
    BELUM: 'outline',
};

export function hitungStatus(
    rows: { status: string }[],
): Record<string, number> {
    return rows.reduce<Record<string, number>>(
        (acc, r) => ({ ...acc, [r.status]: (acc[r.status] ?? 0) + 1 }),
        {},
    );
}

function BarisPeserta({
    p,
    sesiId,
    statusOptions,
}: {
    p: Baris;
    sesiId: string;
    statusOptions: Props['statusOptions'];
}) {
    const [catatan, setCatatan] = useState(p.catatan ?? '');
    const simpan = (status: string) =>
        router.put(
            update.url({ sesi: sesiId, peserta: p.id }),
            { status, catatan },
            { preserveScroll: true },
        );

    return (
        <tr className="border-t align-top">
            <td className="p-3">
                <div className="font-medium">{p.nama}</div>
                <div className="text-xs text-muted-foreground">
                    {p.grups.join(', ')}
                </div>
            </td>
            <td className="p-3">
                <Badge variant={WARNA_STATUS[p.status] ?? 'outline'}>
                    {p.status === 'BELUM' ? 'Belum hadir' : p.status}
                </Badge>
                {p.sumber && (
                    <div className="mt-1 text-xs text-muted-foreground">
                        {p.sumber}
                    </div>
                )}
            </td>
            <td className="p-3 tabular-nums">
                {p.datang ?? '—'}
                {p.pulang ? ` / ${p.pulang}` : ''}
            </td>
            <td className="p-3">
                <div className="flex flex-wrap gap-1">
                    {statusOptions.map((s) => (
                        <Button
                            key={s.value}
                            size="sm"
                            variant={
                                p.status === s.value ? 'default' : 'outline'
                            }
                            onClick={() => simpan(s.value)}
                        >
                            {s.label}
                        </Button>
                    ))}
                </div>
                <Input
                    className="mt-2 h-8"
                    placeholder="Catatan"
                    value={catatan}
                    onChange={(e) => setCatatan(e.target.value)}
                />
            </td>
        </tr>
    );
}

export default function Kehadiran({
    program,
    tanggal,
    sesis,
    sesi,
    peserta,
    riwayat,
    grups,
    statusOptions,
    linkScan,
    filters,
}: Props) {
    const buka = (patch: Record<string, string>) =>
        router.get(
            index.url(program.id),
            { tanggal, sesi: sesi?.id ?? '', ...filters, ...patch },
            { preserveState: true },
        );
    const jumlah = hitungStatus(peserta);
    const namaPeserta = new Map(peserta.map((p) => [p.id, p.nama]));

    return (
        <>
            <Head title={`Kehadiran — ${program.name}`} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Kehadiran
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            <Link
                                href={programShow(program.id)}
                                className="underline"
                            >
                                {program.name}
                            </Link>
                        </p>
                    </div>
                    <Input
                        type="date"
                        className="w-44"
                        value={tanggal}
                        onChange={(e) =>
                            buka({ tanggal: e.target.value, sesi: '' })
                        }
                        aria-label="Tanggal"
                    />
                </div>

                {linkScan && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Link scan petugas
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-2 text-sm">
                            <div>
                                Kamera HP/laptop:{' '}
                                <a
                                    href={linkScan.kamera}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="break-all underline"
                                >
                                    {linkScan.kamera}
                                </a>
                            </div>
                            <div>
                                Ringan (TV box, HP lama, scanner gun):{' '}
                                <a
                                    href={linkScan.ringan}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="break-all underline"
                                >
                                    {linkScan.ringan}
                                </a>
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                className="justify-self-start"
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            'Ganti link scan? Link lama tidak bisa dipakai lagi. Kartu peserta tetap berlaku.',
                                        )
                                    ) {
                                        router.post(rotate.url(program.id));
                                    }
                                }}
                            >
                                Ganti link scan
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {sesis.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Tidak ada sesi pada tanggal ini.
                    </p>
                ) : (
                    <div className="flex flex-wrap gap-2">
                        {sesis.map((s) => (
                            <Button
                                key={s.id}
                                size="sm"
                                variant={
                                    sesi?.id === s.id ? 'default' : 'outline'
                                }
                                onClick={() => buka({ sesi: s.id })}
                            >
                                {s.name} · {s.datang}
                            </Button>
                        ))}
                    </div>
                )}

                {sesi && (
                    <>
                        <div className="flex flex-wrap items-center gap-2 text-sm">
                            {Object.entries(jumlah).map(([status, n]) => (
                                <Badge
                                    key={status}
                                    variant={WARNA_STATUS[status] ?? 'outline'}
                                >
                                    {status === 'BELUM'
                                        ? 'Belum hadir'
                                        : status}
                                    : {n}
                                </Badge>
                            ))}
                            <select
                                aria-label="Grup"
                                className="h-8 rounded-md border bg-background px-2"
                                value={filters.grup}
                                onChange={(e) => buka({ grup: e.target.value })}
                            >
                                <option value="">Semua grup</option>
                                {grups.map((g) => (
                                    <option key={g.id} value={g.id}>
                                        {g.name}
                                    </option>
                                ))}
                            </select>
                            <select
                                aria-label="Status"
                                className="h-8 rounded-md border bg-background px-2"
                                value={filters.status}
                                onChange={(e) =>
                                    buka({ status: e.target.value })
                                }
                            >
                                <option value="">Semua status</option>
                                <option value="BELUM">Belum hadir</option>
                                {statusOptions.map((s) => (
                                    <option key={s.value} value={s.value}>
                                        {s.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="p-3 font-medium">
                                            Peserta
                                        </th>
                                        <th className="p-3 font-medium">
                                            Status
                                        </th>
                                        <th className="p-3 font-medium">
                                            Datang / pulang
                                        </th>
                                        <th className="p-3 font-medium">
                                            Catat manual
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {peserta.map((p) => (
                                        <BarisPeserta
                                            key={p.id}
                                            p={p}
                                            sesiId={sesi.id}
                                            statusOptions={statusOptions}
                                        />
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {riwayat.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Riwayat perubahan
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ul className="divide-y text-sm">
                                        {riwayat.map((r, i) => (
                                            <li key={i} className="py-2">
                                                {new Date(
                                                    r.waktu,
                                                ).toLocaleString('id-ID')}{' '}
                                                · {r.oleh} ·{' '}
                                                {namaPeserta.get(
                                                    r.peserta ?? '',
                                                ) ?? 'Peserta'}
                                                : {r.lama ?? '—'} →{' '}
                                                {r.baru ?? '—'}
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        )}
                    </>
                )}
            </div>
        </>
    );
}

Kehadiran.layout = {
    breadcrumbs: [{ title: 'Beranda', href: dashboard() }],
};
