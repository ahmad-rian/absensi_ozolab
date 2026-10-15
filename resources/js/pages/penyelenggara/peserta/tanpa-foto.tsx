import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, ExternalLink, Search, Upload } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ModalPasFotoAntre } from '@/components/shared/modal-pas-foto-antre';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useAksesPenyelenggara } from '@/hooks/use-akses-penyelenggara';
import { dashboard } from '@/routes/penyelenggara';
import {
    edit,
    foto,
    index as pesertaIndex,
    tanpaFoto,
} from '@/routes/penyelenggara/peserta';

type Baris = {
    id: string;
    full_name: string;
    nis: string | null;
    classroom: string | null;
};

type Props = {
    program: {
        id: string;
        name: string;
        archived: boolean;
        istilah_peserta: string;
        istilah_grup: string;
    };
    adaKolomFoto: boolean;
    peserta: {
        data: Baris[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    ringkasan: {
        total: number;
        berfoto: number;
        tanpa_foto: number;
        persen_berfoto: number;
    };
    grups: { id: string; name: string }[];
    filters: { q: string; grup: string };
};

function Angka({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="rounded-lg border p-4">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-2xl font-bold tabular-nums">{value}</p>
        </div>
    );
}

/** Padanan "Siswa Belum Ada Foto" untuk peserta absensi general. */
export default function PesertaTanpaFoto({
    program,
    adaKolomFoto,
    peserta,
    ringkasan,
    grups,
    filters,
}: Props) {
    const akses = useAksesPenyelenggara();
    const bolehUnggah =
        akses.kelolaPeserta && !program.archived && adaKolomFoto;
    const istilah = program.istilah_peserta.toLowerCase();
    const [cari, setCari] = useState(filters.q);
    // Indeks baris yang modalnya dibuka; juga `key` modal agar antrean segar.
    const [barisFoto, setBarisFoto] = useState<number | null>(null);

    const muat = (patch: Partial<Props['filters']>) =>
        router.get(
            tanpaFoto.url(program.id),
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const kirimCari = (e: FormEvent) => {
        e.preventDefault();
        muat({ q: cari.trim() });
    };

    return (
        <>
            <Head title={`${program.istilah_peserta} belum ada foto`} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {program.istilah_peserta} belum ada foto
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        <Link
                            href={pesertaIndex(program.id)}
                            className="underline"
                        >
                            {program.name}
                        </Link>{' '}
                        · {istilah} aktif yang belum punya foto kartu.
                    </p>
                </div>

                {!adaKolomFoto && (
                    <p className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                        Kategori program ini tidak punya kolom foto. Tambahkan
                        kolom bertipe foto di pengaturan kategori bila kartu
                        perlu foto.
                    </p>
                )}

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Angka
                        label={`Total ${istilah} aktif`}
                        value={ringkasan.total}
                    />
                    <Angka label="Sudah ada foto" value={ringkasan.berfoto} />
                    <Angka
                        label="Belum ada foto"
                        value={ringkasan.tanpa_foto}
                    />
                    <Angka
                        label="Kelengkapan foto"
                        value={`${ringkasan.persen_berfoto}%`}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Daftar</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <div className="flex flex-col gap-3 sm:flex-row">
                            <select
                                aria-label={program.istilah_grup}
                                className="h-9 rounded-md border bg-background px-2 text-sm sm:w-56"
                                value={filters.grup}
                                onChange={(e) => muat({ grup: e.target.value })}
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
                            <form
                                onSubmit={kirimCari}
                                className="flex flex-1 gap-2"
                            >
                                <Input
                                    value={cari}
                                    onChange={(e) => setCari(e.target.value)}
                                    placeholder={`Cari ${istilah}`}
                                    aria-label={`Cari ${istilah}`}
                                />
                                <Button type="submit" variant="outline">
                                    <Search className="size-4" /> Cari
                                </Button>
                            </form>
                        </div>

                        {peserta.data.length === 0 ? (
                            <p className="flex items-center gap-2 py-6 text-sm text-muted-foreground">
                                <CheckCircle2 className="size-4 text-emerald-600" />
                                Semua {istilah} di daftar ini sudah punya foto.
                            </p>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left">
                                        <tr>
                                            <th className="p-3 font-medium">
                                                Nama
                                            </th>
                                            <th className="p-3 font-medium">
                                                {program.istilah_grup}
                                            </th>
                                            <th className="p-3 text-right font-medium">
                                                Aksi
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {peserta.data.map((p, i) => (
                                            <tr key={p.id} className="border-t">
                                                <td className="p-3 font-medium">
                                                    {p.full_name}
                                                </td>
                                                <td className="p-3 text-muted-foreground">
                                                    {p.classroom ?? '—'}
                                                </td>
                                                <td className="p-3">
                                                    <div className="flex justify-end gap-2">
                                                        {bolehUnggah && (
                                                            <Button
                                                                size="sm"
                                                                onClick={() =>
                                                                    setBarisFoto(
                                                                        i,
                                                                    )
                                                                }
                                                            >
                                                                <Upload className="size-4" />{' '}
                                                                Pasang foto
                                                            </Button>
                                                        )}
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={edit(
                                                                    p.id,
                                                                )}
                                                            >
                                                                <ExternalLink className="size-4" />{' '}
                                                                Detail
                                                            </Link>
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        <div className="flex items-center justify-between text-sm text-muted-foreground">
                            <span>
                                Halaman {peserta.current_page} dari{' '}
                                {peserta.last_page} · {peserta.total} {istilah}
                            </span>
                            <div className="flex gap-2">
                                {peserta.prev_page_url && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            router.get(peserta.prev_page_url!)
                                        }
                                    >
                                        Sebelumnya
                                    </Button>
                                )}
                                {peserta.next_page_url && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            router.get(peserta.next_page_url!)
                                        }
                                    >
                                        Berikutnya
                                    </Button>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {barisFoto !== null && (
                <ModalPasFotoAntre
                    key={barisFoto}
                    siswa={peserta.data}
                    mulaiDari={barisFoto}
                    urlUnggah={(id) => foto.url(id)}
                    schoolId=""
                    muatUlang={['ringkasan', 'peserta']}
                    istilah={istilah}
                    onTutup={() => setBarisFoto(null)}
                />
            )}
        </>
    );
}

PesertaTanpaFoto.layout = {
    breadcrumbs: [{ title: 'Beranda', href: dashboard() }],
};
