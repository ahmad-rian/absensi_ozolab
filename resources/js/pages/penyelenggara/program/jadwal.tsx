import { Head, Link, router, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes/penyelenggara';
import { destroy, store } from '@/routes/penyelenggara/jadwal';
import {
    destroy as hapusLibur,
    store as tambahLibur,
} from '@/routes/penyelenggara/libur';
import { show as programShow } from '@/routes/penyelenggara/program';

type Pola = {
    id: string;
    name: string;
    pengulangan: 'mingguan' | 'rentang' | 'sekali';
    hari: number[];
    tanggal: string | null;
    grup: string | null;
    datang_mulai: string;
    datang_selesai: string;
    batas_terlambat: string | null;
    pulang_mulai: string | null;
    pulang_selesai: string | null;
};

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

export const NAMA_HARI = ['', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

export function ringkasPola(p: Pola): string {
    if (p.pengulangan === 'sekali') {
return `Sekali, ${p.tanggal}`;
}

    const hari =
        p.hari.length === 7
            ? 'Setiap hari'
            : p.hari.map((h) => NAMA_HARI[h]).join(', ');

    return p.pengulangan === 'rentang'
        ? `Rentang · ${hari || 'setiap hari'}`
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
    const form = useForm({
        name: '',
        pengulangan: 'mingguan',
        hari: [1, 2, 3, 4, 5] as number[],
        tanggal: '',
        grup_id: '',
        datang_mulai: '07:00',
        datang_selesai: '08:30',
        batas_terlambat: program.fitur.terlambat ? '07:30' : '',
        pulang_mulai: program.fitur.pulang ? '15:00' : '',
        pulang_selesai: program.fitur.pulang ? '17:00' : '',
    });
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
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
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
                                        )}
                                    </li>
                                ))}
                            </ul>

                            {!program.archived && (
                                <form
                                    className="grid gap-3 rounded-md border p-3"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        form.post(store.url(program.id), {
                                            preserveScroll: true,
                                            onSuccess: () => form.reset('name'),
                                        });
                                    }}
                                >
                                    <div className="grid gap-3 sm:grid-cols-3">
                                        <div className="grid gap-1 sm:col-span-2">
                                            <Label htmlFor="nama-sesi">
                                                Nama sesi
                                            </Label>
                                            <Input
                                                id="nama-sesi"
                                                value={form.data.name}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'name',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Manasik Pagi"
                                            />
                                            <InputError
                                                message={form.errors.name}
                                            />
                                        </div>
                                        <div className="grid gap-1">
                                            <Label htmlFor="pengulangan">
                                                Pengulangan
                                            </Label>
                                            <select
                                                id="pengulangan"
                                                className="h-9 rounded-md border bg-background px-2 text-sm"
                                                value={form.data.pengulangan}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'pengulangan',
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                <option value="mingguan">
                                                    Hari tertentu tiap minggu
                                                </option>
                                                <option value="sekali">
                                                    Sekali
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    {form.data.pengulangan === 'mingguan' ? (
                                        <div className="flex flex-wrap gap-3 text-sm">
                                            {[1, 2, 3, 4, 5, 6, 7].map((h) => (
                                                <label
                                                    key={h}
                                                    className="flex items-center gap-1"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={form.data.hari.includes(
                                                            h,
                                                        )}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'hari',
                                                                e.target.checked
                                                                    ? [
                                                                          ...form
                                                                              .data
                                                                              .hari,
                                                                          h,
                                                                      ]
                                                                    : form.data.hari.filter(
                                                                          (x) =>
                                                                              x !==
                                                                              h,
                                                                      ),
                                                            )
                                                        }
                                                    />
                                                    {NAMA_HARI[h]}
                                                </label>
                                            ))}
                                            <InputError
                                                message={form.errors.hari}
                                            />
                                        </div>
                                    ) : (
                                        <div className="grid gap-1 sm:w-48">
                                            <Input
                                                type="date"
                                                min={program.starts_on}
                                                max={program.ends_on}
                                                value={form.data.tanggal}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'tanggal',
                                                        e.target.value,
                                                    )
                                                }
                                                aria-label="Tanggal"
                                            />
                                            <InputError
                                                message={form.errors.tanggal}
                                            />
                                        </div>
                                    )}
                                    <div className="grid gap-3 sm:grid-cols-3">
                                        <div className="grid gap-1">
                                            <Label>Datang</Label>
                                            <div className="flex gap-1">
                                                <Input
                                                    type="time"
                                                    value={
                                                        form.data.datang_mulai
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'datang_mulai',
                                                            e.target.value,
                                                        )
                                                    }
                                                    aria-label="Mulai datang"
                                                />
                                                <Input
                                                    type="time"
                                                    value={
                                                        form.data.datang_selesai
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'datang_selesai',
                                                            e.target.value,
                                                        )
                                                    }
                                                    aria-label="Akhir datang"
                                                />
                                            </div>
                                            <InputError
                                                message={
                                                    form.errors.datang_mulai ??
                                                    form.errors.datang_selesai
                                                }
                                            />
                                        </div>
                                        {program.fitur.terlambat && (
                                            <div className="grid gap-1">
                                                <Label htmlFor="terlambat">
                                                    Terlambat setelah
                                                </Label>
                                                <Input
                                                    id="terlambat"
                                                    type="time"
                                                    value={
                                                        form.data
                                                            .batas_terlambat
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'batas_terlambat',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        form.errors
                                                            .batas_terlambat
                                                    }
                                                />
                                            </div>
                                        )}
                                        {program.fitur.pulang && (
                                            <div className="grid gap-1">
                                                <Label>Pulang</Label>
                                                <div className="flex gap-1">
                                                    <Input
                                                        type="time"
                                                        value={
                                                            form.data
                                                                .pulang_mulai
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'pulang_mulai',
                                                                e.target.value,
                                                            )
                                                        }
                                                        aria-label="Mulai pulang"
                                                    />
                                                    <Input
                                                        type="time"
                                                        value={
                                                            form.data
                                                                .pulang_selesai
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'pulang_selesai',
                                                                e.target.value,
                                                            )
                                                        }
                                                        aria-label="Akhir pulang"
                                                    />
                                                </div>
                                                <InputError
                                                    message={
                                                        form.errors
                                                            .pulang_mulai ??
                                                        form.errors
                                                            .pulang_selesai
                                                    }
                                                />
                                            </div>
                                        )}
                                    </div>
                                    {grups.length > 0 && (
                                        <select
                                            aria-label={program.istilah_grup}
                                            className="h-9 rounded-md border bg-background px-2 text-sm sm:w-64"
                                            value={form.data.grup_id}
                                            onChange={(e) =>
                                                form.setData(
                                                    'grup_id',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <option value="">
                                                Semua{' '}
                                                {program.istilah_grup.toLowerCase()}
                                            </option>
                                            {grups.map((g) => (
                                                <option key={g.id} value={g.id}>
                                                    Khusus {g.name}
                                                </option>
                                            ))}
                                        </select>
                                    )}
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                        className="justify-self-start"
                                    >
                                        Tambah sesi
                                    </Button>
                                </form>
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
