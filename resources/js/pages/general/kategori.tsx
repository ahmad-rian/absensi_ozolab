import { Head, router, usePage } from '@inertiajs/react';
import { Archive, ArchiveRestore, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { FilterPenyelenggara } from '@/components/general/filter-penyelenggara';
import { Halaman } from '@/components/general/halaman';
import { MasukPenyelenggara } from '@/components/general/masuk-penyelenggara';
import { TambahKategori } from '@/components/general/tambah-kategori';
import type { TemplateKategori } from '@/components/general/tambah-kategori';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard, kategori as kategoriRoute } from '@/routes/general';
import { arsip, destroy } from '@/routes/general/kategori';

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
            bisa_dihapus: boolean;
            penyelenggara: { id: string; name: string | null };
        }[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    penyelenggaras: { id: string; name: string }[];
    filters: { penyelenggara: string; q: string };
    templates: TemplateKategori[];
};

type Baris = Props['kategoris']['data'][number];

/** Kategori semua penyelenggara (haji, pelatihan, kantor, …): tambah, edit, arsip, hapus. */
export default function GeneralKategori({
    kategoris,
    penyelenggaras,
    filters,
    templates,
}: Props) {
    const [tambah, setTambah] = useState(false);
    const { errors } = usePage<{ errors: Record<string, string> }>().props;

    const ubahArsip = (k: Baris) => {
        const pesan = k.archived
            ? `Aktifkan lagi kategori "${k.name}"?`
            : `Arsipkan kategori "${k.name}"? Program dan pesertanya tetap tersimpan.`;

        if (window.confirm(pesan)) {
            router.post(arsip.url(k.id), {}, { preserveScroll: true });
        }
    };

    const hapus = (k: Baris) => {
        if (
            window.confirm(
                `Hapus kategori "${k.name}" milik ${k.penyelenggara.name ?? 'penyelenggara'}? Tindakan ini tidak bisa dibatalkan.`,
            )
        ) {
            router.delete(destroy.url(k.id), { preserveScroll: true });
        }
    };

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
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Kategori
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Jenis kegiatan tiap penyelenggara — haji/umroh,
                            pelatihan, pengajian, kantor, event. Kategori yang
                            sudah punya program diarsip, bukan dihapus.
                        </p>
                    </div>
                    <Button onClick={() => setTambah(true)}>
                        <Plus className="size-4" /> Tambah kategori
                    </Button>
                </div>

                <InputError message={errors.kategori} />

                <FilterPenyelenggara
                    penyelenggaras={penyelenggaras}
                    nilai={filters.penyelenggara}
                    cari={filters.q}
                    placeholder="Cari kategori…"
                    onUbah={muat}
                />

                {kategoris.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-lg border py-10 text-center text-sm text-muted-foreground">
                        Belum ada kategori yang cocok.
                        <Button
                            variant="outline"
                            onClick={() => setTambah(true)}
                        >
                            <Plus className="size-4" /> Tambah kategori pertama
                        </Button>
                    </div>
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
                                        <td className="p-3">
                                            <div className="flex justify-end gap-1.5">
                                                <MasukPenyelenggara
                                                    penyelenggaraId={
                                                        k.penyelenggara.id
                                                    }
                                                    kembali={`/penyelenggara/kategori/${k.id}`}
                                                    label="Edit"
                                                />
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => ubahArsip(k)}
                                                >
                                                    {k.archived ? (
                                                        <ArchiveRestore className="size-4" />
                                                    ) : (
                                                        <Archive className="size-4" />
                                                    )}
                                                    {k.archived
                                                        ? 'Aktifkan'
                                                        : 'Arsip'}
                                                </Button>
                                                {k.bisa_dihapus && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        aria-label={`Hapus ${k.name}`}
                                                        onClick={() => hapus(k)}
                                                    >
                                                        <Trash2 className="size-4 text-destructive" />
                                                    </Button>
                                                )}
                                            </div>
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

            <TambahKategori
                open={tambah}
                onOpenChange={setTambah}
                penyelenggaras={penyelenggaras}
                templates={templates}
                penyelenggaraAwal={filters.penyelenggara}
            />
        </>
    );
}

GeneralKategori.layout = {
    breadcrumbs: [
        { title: 'Absensi General', href: dashboard() },
        { title: 'Kategori', href: kategoriRoute() },
    ],
};
