import { Head, router } from '@inertiajs/react';
import { FilterPenyelenggara } from '@/components/general/filter-penyelenggara';
import { Halaman } from '@/components/general/halaman';
import { MasukPenyelenggara } from '@/components/general/masuk-penyelenggara';
import { Badge } from '@/components/ui/badge';
import { dashboard, kategori as kategoriRoute } from '@/routes/general';

type Props = {
    kategoris: {
        data: {
            id: string;
            name: string;
            template: string | null;
            istilah_peserta: string;
            istilah_grup: string;
            archived: boolean;
            programs_count: number;
            penyelenggara: { id: string; name: string | null };
        }[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    penyelenggaras: { id: string; name: string }[];
    filters: { penyelenggara: string; q: string };
};

/** Kategori semua penyelenggara (haji, pelatihan, kantor, …), read-only. */
export default function GeneralKategori({
    kategoris,
    penyelenggaras,
    filters,
}: Props) {
    const muat = (patch: Partial<Props['filters']>) =>
        router.get(
            kategoriRoute.url(),
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Kategori" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Kategori
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Jenis kegiatan tiap penyelenggara — haji/umroh,
                        pelatihan, pengajian, kantor, event. Ubah kategori dari
                        area penyelenggaranya.
                    </p>
                </div>

                <FilterPenyelenggara
                    penyelenggaras={penyelenggaras}
                    nilai={filters.penyelenggara}
                    cari={filters.q}
                    placeholder="Cari kategori…"
                    onUbah={muat}
                />

                {kategoris.data.length === 0 ? (
                    <p className="rounded-lg border py-10 text-center text-sm text-muted-foreground">
                        Belum ada kategori yang cocok.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="p-3 font-medium">
                                        Kategori
                                    </th>
                                    <th className="p-3 font-medium">
                                        Penyelenggara
                                    </th>
                                    <th className="p-3 font-medium">Istilah</th>
                                    <th className="p-3 text-right font-medium">
                                        Program aktif
                                    </th>
                                    <th className="p-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {kategoris.data.map((k) => (
                                    <tr key={k.id} className="border-t">
                                        <td className="p-3">
                                            <span className="font-medium">
                                                {k.name}
                                            </span>
                                            {k.archived && (
                                                <Badge
                                                    variant="secondary"
                                                    className="ml-2"
                                                >
                                                    Arsip
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="p-3 text-muted-foreground">
                                            {k.penyelenggara.name ?? '—'}
                                        </td>
                                        <td className="p-3 text-muted-foreground">
                                            {k.istilah_peserta} ·{' '}
                                            {k.istilah_grup}
                                        </td>
                                        <td className="p-3 text-right tabular-nums">
                                            {k.programs_count}
                                        </td>
                                        <td className="p-3 text-right">
                                            <MasukPenyelenggara
                                                penyelenggaraId={
                                                    k.penyelenggara.id
                                                }
                                                kembali={`/penyelenggara/kategori/${k.id}`}
                                                label="Buka"
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Halaman
                    prev={kategoris.prev_page_url}
                    next={kategoris.next_page_url}
                    total={kategoris.total}
                    satuan="kategori"
                />
            </div>
        </>
    );
}

GeneralKategori.layout = {
    breadcrumbs: [
        { title: 'Absensi General', href: dashboard() },
        { title: 'Kategori', href: kategoriRoute() },
    ],
};
