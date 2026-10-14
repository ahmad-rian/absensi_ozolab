import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { NAMA_HARI, PolaForm } from '@/components/penyelenggara/pola-form';
import type { Pola } from '@/components/penyelenggara/pola-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes/penyelenggara';
import { destroy } from '@/routes/penyelenggara/jadwal';
import {
    destroy as hapusLibur,
    store as tambahLibur,
} from '@/routes/penyelenggara/libur';
import { show as programShow } from '@/routes/penyelenggara/program';

type Props = {
    program: {
        id: string;
        name: string;
        starts_on: string;
        ends_on: string;
        timezone: string;
        archived: boolean;
        istilah_grup: string;
        fitur: { terlambat: boolean; pulang: boolean };
    };
    polas: Pola[];
    liburs: { id: string; tanggal: string; keterangan: string | null }[];
    kalender: {
        tanggal: string;
        sesis: {
            id: string;
            name: string;
            grup: string | null;
            datang: string;
            pulang: string | null;
        }[];
    }[];
    grups: { id: string; name: string }[];
};

export function ringkasPola(p: Pola): string {
    if (p.pengulangan === 'sekali') {
        return `Sekali, ${p.tanggal}`;
    }

    const hari =
        p.hari.length === 7
            ? 'Setiap hari'
            : p.hari.map((h) => NAMA_HARI[h]).join(', ');

    return p.pengulangan === 'rentang'
        ? `${p.tanggal_mulai} s/d ${p.tanggal_selesai} · ${hari || 'setiap hari'}`
        : hari;
}

const tanggalPanjang = (t: string) =>
    new Intl.DateTimeFormat('id-ID', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    }).format(new Date(`${t}T00:00:00`));

export default function Jadwal({
    program,
    polas,
    liburs,
    kalender,
    grups,
}: Props) {
    const [diubah, setDiubah] = useState<string | null>(null);
    const libur = useForm({ tanggal: '', keterangan: '' });
    const tanggalLibur = new Set(liburs.map((l) => l.tanggal));

    return (
        <>
            <Head title={`Jadwal — ${program.name}`} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Jadwal
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        <Link
                            href={programShow(program.id)}
                            className="underline"
                        >
                            {program.name}
                        </Link>{' '}
                        · semua jam dalam zona {program.timezone}
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-[3fr_2fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Pola sesi
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            {polas.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada sesi. Tambahkan di bawah.
                                </p>
                            )}
                            <ul className="divide-y text-sm">
                                {polas.map((p) => (
                                    <li
                                        key={p.id}
                                        className="flex flex-wrap items-center justify-between gap-2 py-2"
                                    >
                                        <span>
                                            <span className="font-medium">
                                                {p.name}
                                            </span>{' '}
                                            {p.grup && (
                                                <Badge variant="outline">
                                                    {p.grup}
                                                </Badge>
                                            )}
                                            <span className="block text-xs text-muted-foreground">
                                                {ringkasPola(p)} · datang{' '}
                                                {p.datang_mulai}–
                                                {p.datang_selesai}
                                                {p.batas_terlambat &&
                                                    ` · terlambat setelah ${p.batas_terlambat}`}
                                                {p.pulang_mulai &&
                                                    ` · pulang ${p.pulang_mulai}–${p.pulang_selesai}`}
                                            </span>
                                        </span>
                                        {!program.archived && (
                                            <span className="flex gap-1">
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        setDiubah(
                                                            diubah === p.id
                                                                ? null
                                                                : p.id,
                                                        )
                                                    }
                                                >
                                                    Ubah
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        window.confirm(
                                                            `Hapus sesi "${p.name}"? Sesi mendatang ikut terhapus; sesi yang sudah lewat dan kehadirannya tetap tersimpan.`,
                                                        ) &&
                                                        router.delete(
                                                            destroy.url(p.id),
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Hapus
                                                </Button>
                                            </span>
                                        )}
                                        {diubah === p.id && (
                                            <div className="w-full">
                                                <PolaForm
                                                    program={program}
                                                    grups={grups}
                                                    pola={p}
                                                    onSelesai={() =>
                                                        setDiubah(null)
                                                    }
                                                />
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>

                            {!program.archived && (
                                <PolaForm program={program} grups={grups} />
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Hari libur
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3 text-sm">
                            <ul className="divide-y">
                                {liburs.map((l) => (
                                    <li
                                        key={l.id}
                                        className="flex items-center justify-between py-2"
                                    >
                                        <span>
                                            {tanggalPanjang(l.tanggal)}{' '}
                                            {l.keterangan && (
                                                <span className="text-muted-foreground">
                                                    · {l.keterangan}
                                                </span>
                                            )}
                                        </span>
                                        {!program.archived && (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    window.confirm(
                                                        `Hapus libur ${l.tanggal}? Sesi di tanggal itu kembali dihitung.`,
                                                    ) &&
                                                    router.delete(
                                                        hapusLibur.url(l.id),
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Hapus
                                            </Button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                            {!program.archived && (
                                <form
                                    className="flex flex-wrap gap-2"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        libur.post(
                                            tambahLibur.url(program.id),
                                            {
                                                preserveScroll: true,
                                                onSuccess: () => libur.reset(),
                                            },
                                        );
                                    }}
                                >
                                    <Input
                                        type="date"
                                        className="w-40"
                                        value={libur.data.tanggal}
                                        onChange={(e) =>
                                            libur.setData(
                                                'tanggal',
                                                e.target.value,
                                            )
                                        }
                                        aria-label="Tanggal libur"
                                    />
                                    <Input
                                        className="flex-1"
                                        placeholder="Keterangan"
                                        value={libur.data.keterangan}
                                        onChange={(e) =>
                                            libur.setData(
                                                'keterangan',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={libur.processing}
                                    >
                                        Tambah
                                    </Button>
                                    <InputError
                                        message={libur.errors.tanggal}
                                    />
                                </form>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Kalender sesi
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {kalender.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Belum ada sesi terjadwal.
                            </p>
                        ) : (
                            <ul className="grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                {kalender.map((hari) => (
                                    <li
                                        key={hari.tanggal}
                                        className={`rounded-md border p-2 ${tanggalLibur.has(hari.tanggal) ? 'opacity-50' : ''}`}
                                    >
                                        <div className="font-medium">
                                            {tanggalPanjang(hari.tanggal)}{' '}
                                            {tanggalLibur.has(hari.tanggal) && (
                                                <Badge variant="secondary">
                                                    Libur
                                                </Badge>
                                            )}
                                        </div>
                                        {hari.sesis.map((s) => (
                                            <div
                                                key={s.id}
                                                className="text-xs text-muted-foreground"
                                            >
                                                {s.name}
                                                {s.grup
                                                    ? ` (${s.grup})`
                                                    : ''} · {s.datang}
                                                {s.pulang
                                                    ? ` / ${s.pulang}`
                                                    : ''}
                                            </div>
                                        ))}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Jadwal.layout = {
    breadcrumbs: [{ title: 'Beranda', href: dashboard() }],
};
