import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes/penyelenggara';
import {
    destroy as hapusGrup,
    pendamping as simpanPendamping,
    store as tambahGrup,
} from '@/routes/penyelenggara/grup';
import { index as jadwalIndex } from '@/routes/penyelenggara/jadwal';
import {
    edit as kartuEdit,
    zip as kartuZip,
} from '@/routes/penyelenggara/kartu';
import { index as kehadiranIndex } from '@/routes/penyelenggara/kehadiran';
import { index as pesertaIndex } from '@/routes/penyelenggara/peserta';
import { arsip, index, salin, update } from '@/routes/penyelenggara/program';
import { rentangTanggal } from './index';

type Grup = {
    id: string;
    name: string;
    parent_id: string | null;
    urutan: number;
    peserta_count: number;
    pendamping: { id: string; name: string }[];
};

type Props = {
    program: {
        id: string;
        name: string;
        kategori: string | null;
        istilah_peserta: string;
        istilah_grup: string;
        starts_on: string;
        ends_on: string;
        timezone: string;
        archived: boolean;
        peserta_count: number;
    };
    grups: Grup[];
    pendampingOptions: { id: string; name: string }[];
    errors: Record<string, string>;
};

/** Urutkan grup jadi daftar pohon (induk lalu anak-anaknya) beserta kedalamannya. */
export function susunPohon(grups: Grup[]): { grup: Grup; depth: number }[] {
    const anak = new Map<string | null, Grup[]>();

    for (const g of grups) {
        anak.set(g.parent_id, [...(anak.get(g.parent_id) ?? []), g]);
    }

    const hasil: { grup: Grup; depth: number }[] = [];
    const jalan = (parent: string | null, depth: number) => {
        for (const g of anak.get(parent) ?? []) {
            hasil.push({ grup: g, depth });
            jalan(g.id, depth + 1);
        }
    };
    jalan(null, 0);

    return hasil;
}

function BarisGrup({
    grup,
    depth,
    program,
    pendampingOptions,
}: {
    grup: Grup;
    depth: number;
    program: Props['program'];
    pendampingOptions: Props['pendampingOptions'];
}) {
    const [nama, setNama] = useState('');
    const [atur, setAtur] = useState(false);
    const [pilih, setPilih] = useState(grup.pendamping.map((p) => p.id));

    return (
        <li className="grid gap-2 py-2" style={{ paddingLeft: depth * 20 }}>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span>
                    <span className="font-medium">{grup.name}</span>{' '}
                    <span className="text-xs text-muted-foreground">
                        {grup.peserta_count}{' '}
                        {program.istilah_peserta.toLowerCase()}
                        {grup.pendamping.length > 0 &&
                            ` · ${grup.pendamping.map((p) => p.name).join(', ')}`}
                    </span>
                </span>
                {!program.archived && (
                    <span className="flex gap-1">
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => setAtur(!atur)}
                        >
                            Pendamping
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() =>
                                router.delete(hapusGrup.url(grup.id), {
                                    preserveScroll: true,
                                })
                            }
                        >
                            Hapus
                        </Button>
                    </span>
                )}
            </div>
            {atur && (
                <div className="flex flex-wrap items-center gap-3 rounded-md border p-2 text-sm">
                    {pendampingOptions.length === 0 && (
                        <span className="text-muted-foreground">
                            Belum ada akun pendamping. Undang lewat menu Tim.
                        </span>
                    )}
                    {pendampingOptions.map((u) => (
                        <label key={u.id} className="flex items-center gap-1">
                            <input
                                type="checkbox"
                                checked={pilih.includes(u.id)}
                                onChange={(e) =>
                                    setPilih(
                                        e.target.checked
                                            ? [...pilih, u.id]
                                            : pilih.filter((id) => id !== u.id),
                                    )
                                }
                            />
                            {u.name}
                        </label>
                    ))}
                    <Button
                        size="sm"
                        onClick={() =>
                            router.put(
                                simpanPendamping.url(grup.id),
                                { user_ids: pilih },
                                {
                                    preserveScroll: true,
                                    onSuccess: () => setAtur(false),
                                },
                            )
                        }
                    >
                        Simpan
                    </Button>
                </div>
            )}
            {!program.archived && (
                <div className="flex gap-2">
                    <Input
                        className="h-8 max-w-xs"
                        placeholder={`Sub-${program.istilah_grup.toLowerCase()} baru`}
                        value={nama}
                        onChange={(e) => setNama(e.target.value)}
                    />
                    <Button
                        size="sm"
                        variant="outline"
                        disabled={!nama.trim()}
                        onClick={() =>
                            router.post(
                                tambahGrup.url(program.id),
                                { name: nama, parent_id: grup.id },
                                {
                                    preserveScroll: true,
                                    onSuccess: () => setNama(''),
                                },
                            )
                        }
                    >
                        Tambah
                    </Button>
                </div>
            )}
        </li>
    );
}

export default function ProgramShow({
    program,
    grups,
    pendampingOptions,
    errors,
}: Props) {
    const pohon = susunPohon(grups);

    return (
        <>
            <Head title={program.name} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            {program.name}{' '}
                            {program.archived && (
                                <Badge variant="secondary">Arsip</Badge>
                            )}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {program.kategori} ·{' '}
                            {rentangTanggal(program.starts_on, program.ends_on)}{' '}
                            · {program.timezone}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild>
                            <Link href={pesertaIndex(program.id)}>
                                {program.peserta_count}{' '}
                                {program.istilah_peserta}
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={jadwalIndex(program.id)}>Jadwal</Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={kehadiranIndex(program.id)}>
                                Kehadiran
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={kartuEdit(program.id)}>
                                Desain kartu
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={kartuZip.url(program.id)}>
                                Unduh semua kartu (ZIP)
                            </a>
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => router.post(arsip.url(program.id))}
                        >
                            {program.archived ? 'Aktifkan lagi' : 'Arsipkan'}
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {program.istilah_grup}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <InputError message={errors.grup} />
                            {pohon.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada{' '}
                                    {program.istilah_grup.toLowerCase()}.
                                </p>
                            ) : (
                                <ul className="divide-y">
                                    {pohon.map(({ grup, depth }) => (
                                        <BarisGrup
                                            key={grup.id}
                                            grup={grup}
                                            depth={depth}
                                            program={program}
                                            pendampingOptions={
                                                pendampingOptions
                                            }
                                        />
                                    ))}
                                </ul>
                            )}
                            {!program.archived && (
                                <Form
                                    {...tambahGrup.form(program.id)}
                                    resetOnSuccess
                                    options={{ preserveScroll: true }}
                                    className="mt-4 flex gap-2"
                                >
                                    {({ processing }) => (
                                        <>
                                            <Input
                                                name="name"
                                                placeholder={`${program.istilah_grup} baru, mis. Kloter 12`}
                                                className="max-w-xs"
                                                required
                                            />
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                disabled={processing}
                                            >
                                                Tambah
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            )}
                        </CardContent>
                    </Card>

                    <div className="grid content-start gap-6">
                        {!program.archived && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Ubah program
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Form
                                        {...update.form(program.id)}
                                        className="grid gap-3"
                                    >
                                        {({ processing, errors: e }) => (
                                            <>
                                                <Input
                                                    name="name"
                                                    defaultValue={program.name}
                                                    aria-label="Nama"
                                                />
                                                <div className="grid grid-cols-2 gap-2">
                                                    <Input
                                                        name="starts_on"
                                                        type="date"
                                                        defaultValue={
                                                            program.starts_on
                                                        }
                                                        aria-label="Mulai"
                                                    />
                                                    <Input
                                                        name="ends_on"
                                                        type="date"
                                                        defaultValue={
                                                            program.ends_on
                                                        }
                                                        aria-label="Selesai"
                                                    />
                                                </div>
                                                <InputError
                                                    message={e.ends_on}
                                                />
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    disabled={processing}
                                                >
                                                    Simpan
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                </CardContent>
                            </Card>
                        )}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Salin ke program baru
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    {...salin.form(program.id)}
                                    className="grid gap-3"
                                >
                                    {({ processing, errors: e }) => (
                                        <>
                                            <Label className="text-xs text-muted-foreground">
                                                Struktur{' '}
                                                {program.istilah_grup.toLowerCase()}{' '}
                                                ikut; peserta tidak.
                                            </Label>
                                            <Input
                                                name="name"
                                                placeholder="Nama program baru"
                                                required
                                            />
                                            <div className="grid grid-cols-2 gap-2">
                                                <Input
                                                    name="starts_on"
                                                    type="date"
                                                    required
                                                    aria-label="Mulai"
                                                />
                                                <Input
                                                    name="ends_on"
                                                    type="date"
                                                    required
                                                    aria-label="Selesai"
                                                />
                                            </div>
                                            <InputError
                                                message={e.ends_on ?? e.name}
                                            />
                                            <Button
                                                type="submit"
                                                size="sm"
                                                variant="outline"
                                                disabled={processing}
                                            >
                                                Salin
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

ProgramShow.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Program', href: index() },
    ],
};
