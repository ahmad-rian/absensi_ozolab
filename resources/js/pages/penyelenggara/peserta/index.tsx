import { Form, Head, Link, router } from '@inertiajs/react';
import { Plus, Upload } from 'lucide-react';
import InputError from '@/components/input-error';
import { FilterKolom } from '@/components/shared/filter-kolom';
import type { KolomFilter } from '@/components/shared/filter-kolom';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useAksesPenyelenggara } from '@/hooks/use-akses-penyelenggara';
import { dashboard } from '@/routes/penyelenggara';
import { pratinjau } from '@/routes/penyelenggara/impor';
import { create, edit, index, tanpaFoto } from '@/routes/penyelenggara/peserta';
import {
    dashboard as programDashboard,
    show as programShow,
} from '@/routes/penyelenggara/program';

type Row = {
    id: string;
    nama: string;
    data: Record<string, string | null>;
    grups: string[];
    status: string;
    archived: boolean;
    thumb: string | null;
};

type Props = {
    program: {
        id: string;
        name: string;
        archived: boolean;
        istilah_peserta: string;
        istilah_grup: string;
    };
    kolom: { key: string; label: string }[];
    peserta: {
        data: Row[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    grups: { id: string; name: string; parent_id: string | null }[];
    tanpaGrup: boolean;
    kuota: { terpakai: number; kuota: number | null };
    filters: {
        q: string;
        grup: string;
        arsip: boolean;
        f: Record<string, string> | string[];
    };
    kolomFilter: KolomFilter[];
};

export const STATUS_KARTU: Record<string, string> = {
    draft: 'Belum digenerate',
    processing: 'Diproses',
    completed: 'Kartu siap',
    failed: 'Gagal',
};

export default function PesertaIndex({
    program,
    kolom,
    peserta,
    grups,
    tanpaGrup,
    kuota,
    filters,
    kolomFilter,
}: Props) {
    const akses = useAksesPenyelenggara();
    const bolehKelola = akses.kelolaPeserta && !program.archived;
    const cari = (patch: Partial<Props['filters']>) =>
        router.get(
            index.url(program.id),
            { ...filters, ...patch },
            { preserveState: true },
        );
    const ekstra = kolom.filter((k) => k.key !== 'nama');

    return (
        <>
            <Head title={`${program.istilah_peserta} — ${program.name}`} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            {program.istilah_peserta}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            <Link
                                href={
                                    akses.program
                                        ? programShow(program.id)
                                        : programDashboard(program.id)
                                }
                                className="underline"
                            >
                                {program.name}
                            </Link>{' '}
                            · {peserta.total}{' '}
                            {program.istilah_peserta.toLowerCase()} · kuota{' '}
                            {kuota.terpakai}/{kuota.kuota ?? '∞'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={tanpaFoto(program.id)}>
                                Belum ada foto
                            </Link>
                        </Button>
                        {bolehKelola && (
                            <Button asChild>
                                <Link href={create(program.id)}>
                                    <Plus className="size-4" /> Tambah{' '}
                                    {program.istilah_peserta.toLowerCase()}
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {bolehKelola && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Upload className="size-4" /> Impor dari
                                Excel/CSV
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-2 text-sm">
                            <p className="text-muted-foreground">
                                Baris pertama berisi judul kolom sesuai kolom
                                kategori ({kolom.map((k) => k.label).join(', ')}
                                , …), ditambah kolom "Grup" bila perlu. Nama
                                grup harus sama persis. Anda melihat pratinjau
                                dulu sebelum data disimpan.
                            </p>
                            <Form
                                {...pratinjau.form(program.id)}
                                encType="multipart/form-data"
                                className="flex flex-wrap items-start gap-2"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-1">
                                            <Input
                                                type="file"
                                                name="file"
                                                accept=".xlsx,.csv"
                                                required
                                            />
                                            <InputError message={errors.file} />
                                        </div>
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            Lihat pratinjau
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}

                <div className="flex flex-wrap items-center gap-2">
                    <Input
                        className="max-w-xs"
                        placeholder={`Cari ${program.istilah_peserta.toLowerCase()}…`}
                        defaultValue={filters.q}
                        onKeyDown={(e) =>
                            e.key === 'Enter' &&
                            cari({ q: e.currentTarget.value })
                        }
                    />
                    <select
                        aria-label={program.istilah_grup}
                        className="h-9 rounded-md border bg-background px-2 text-sm"
                        value={filters.grup}
                        onChange={(e) => cari({ grup: e.target.value })}
                    >
                        <option value="">
                            Semua {program.istilah_grup.toLowerCase()}
                        </option>
                        {grups.map((g) => (
                            <option key={g.id} value={g.id}>
                                {g.name}
                            </option>
                        ))}
                    </select>
                    <FilterKolom
                        kolom={kolomFilter}
                        nilai={Array.isArray(filters.f) ? {} : filters.f}
                        onUbah={(f) => cari({ f })}
                    />
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={filters.arsip}
                            onChange={(e) => cari({ arsip: e.target.checked })}
                        />{' '}
                        Tampilkan yang diarsip
                    </label>
                </div>

                {tanpaGrup ? (
                    <p className="text-sm text-muted-foreground">
                        Belum ditugaskan ke {program.istilah_grup.toLowerCase()}{' '}
                        mana pun.
                    </p>
                ) : peserta.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Belum ada data.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="p-3 font-medium">Nama</th>
                                    {ekstra.map((k) => (
                                        <th
                                            key={k.key}
                                            className="p-3 font-medium"
                                        >
                                            {k.label}
                                        </th>
                                    ))}
                                    <th className="p-3 font-medium">
                                        {program.istilah_grup}
                                    </th>
                                    <th className="p-3 font-medium">Kartu</th>
                                </tr>
                            </thead>
                            <tbody>
                                {peserta.data.map((p) => (
                                    <tr key={p.id} className="border-t">
                                        <td className="p-3">
                                            <Link
                                                href={edit.url(p.id)}
                                                className="flex items-center gap-2 font-medium hover:underline"
                                            >
                                                {p.thumb ? (
                                                    <img
                                                        src={p.thumb}
                                                        alt=""
                                                        className="size-8 rounded object-cover"
                                                        loading="lazy"
                                                    />
                                                ) : (
                                                    <span className="size-8 rounded bg-muted" />
                                                )}
                                                {p.nama}
                                            </Link>
                                        </td>
                                        {ekstra.map((k) => (
                                            <td
                                                key={k.key}
                                                className="p-3 text-muted-foreground"
                                            >
                                                {p.data[k.key] ?? '—'}
                                            </td>
                                        ))}
                                        <td className="p-3">
                                            {p.grups.join(', ') || '—'}
                                        </td>
                                        <td className="p-3">
                                            {p.archived ? (
                                                <Badge variant="secondary">
                                                    Diarsip
                                                </Badge>
                                            ) : (
                                                <Badge
                                                    variant={
                                                        p.status === 'completed'
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                >
                                                    {STATUS_KARTU[p.status] ??
                                                        p.status}
                                                </Badge>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <div className="flex gap-2">
                    {peserta.prev_page_url && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.get(peserta.prev_page_url!)}
                        >
                            Sebelumnya
                        </Button>
                    )}
                    {peserta.next_page_url && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.get(peserta.next_page_url!)}
                        >
                            Berikutnya
                        </Button>
                    )}
                </div>
            </div>
        </>
    );
}

PesertaIndex.layout = {
    breadcrumbs: [{ title: 'Beranda', href: dashboard() }],
};
