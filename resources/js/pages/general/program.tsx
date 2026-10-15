import { Head, router } from '@inertiajs/react';
import { FilterPenyelenggara } from '@/components/general/filter-penyelenggara';
import { Halaman } from '@/components/general/halaman';
import { MasukPenyelenggara } from '@/components/general/masuk-penyelenggara';
import { Badge } from '@/components/ui/badge';
import { dashboard, program as programRoute } from '@/routes/general';

type Props = {
    programs: {
        data: {
            id: string;
            name: string;
            starts_on: string;
            ends_on: string;
            archived: boolean;
            kategori: string | null;
            peserta: number;
            penyelenggara: { id: string; name: string | null };
            url: string;
        }[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    penyelenggaras: { id: string; name: string }[];
    filters: { penyelenggara: string; q: string; arsip: boolean };
};

const tgl = (t: string) =>
    new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(
        new Date(`${t}T00:00:00`),
    );

/** Program semua penyelenggara. "Buka" masuk ke halaman program itu. */
export default function GeneralProgram({
    programs,
    penyelenggaras,
    filters,
}: Props) {
    const muat = (patch: Partial<Props['filters']>) =>
        router.get(
            programRoute.url(),
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );
    const hariIni = new Date().toISOString().slice(0, 10);

    return (
        <>
            <Head title="Program" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Program
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Semua periode kegiatan lintas penyelenggara.
                    </p>
                </div>

                <div className="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                    <FilterPenyelenggara
                        penyelenggaras={penyelenggaras}
                        nilai={filters.penyelenggara}
                        cari={filters.q}
                        placeholder="Cari program…"
                        onUbah={muat}
                    />
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={filters.arsip}
                            onChange={(e) => muat({ arsip: e.target.checked })}
                        />
                        Tampilkan yang diarsip
                    </label>
                </div>

                {programs.data.length === 0 ? (
                    <p className="rounded-lg border py-10 text-center text-sm text-muted-foreground">
                        Belum ada program yang cocok.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="p-3 font-medium">Program</th>
                                    <th className="p-3 font-medium">
                                        Penyelenggara
                                    </th>
                                    <th className="p-3 font-medium">Periode</th>
                                    <th className="p-3 text-right font-medium">
                                        Peserta
                                    </th>
                                    <th className="p-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {programs.data.map((p) => {
                                    const berjalan =
                                        !p.archived &&
                                        p.starts_on <= hariIni &&
                                        p.ends_on >= hariIni;

                                    return (
                                        <tr key={p.id} className="border-t">
                                            <td className="p-3">
                                                <span className="font-medium">
                                                    {p.name}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {p.kategori ?? '—'}
                                                </span>
                                            </td>
                                            <td className="p-3 text-muted-foreground">
                                                {p.penyelenggara.name ?? '—'}
                                            </td>
                                            <td className="p-3 whitespace-nowrap">
                                                {tgl(p.starts_on)} –{' '}
                                                {tgl(p.ends_on)}
                                                {berjalan && (
                                                    <Badge className="ml-2">
                                                        Berjalan
                                                    </Badge>
                                                )}
                                                {p.archived && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="ml-2"
                                                    >
                                                        Arsip
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {p.peserta}
                                            </td>
                                            <td className="p-3 text-right">
                                                <MasukPenyelenggara
                                                    penyelenggaraId={
                                                        p.penyelenggara.id
                                                    }
                                                    kembali={p.url}
                                                    label="Buka"
                                                />
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}

                <Halaman
                    prev={programs.prev_page_url}
                    next={programs.next_page_url}
                    total={programs.total}
                    satuan="program"
                />
            </div>
        </>
    );
}

GeneralProgram.layout = {
    breadcrumbs: [
        { title: 'Absensi General', href: dashboard() },
        { title: 'Program', href: programRoute() },
    ],
};
