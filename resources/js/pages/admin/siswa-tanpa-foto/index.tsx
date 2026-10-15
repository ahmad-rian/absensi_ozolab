import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Aperture,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    ImageOff,
    Search,
    Upload,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ModalPasFotoAntre } from '@/components/shared/modal-pas-foto-antre';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';

type Opsi = { id: string; name: string };

type SiswaTanpaFoto = {
    id: string;
    full_name: string;
    nis: string | null;
    no_absen: string | null;
    classroom: string | null;
};

type Ringkasan = {
    total: number;
    berfoto: number;
    tanpa_foto: number;
    persen_berfoto: number;
};

type PageProps = {
    siswa: {
        data: SiswaTanpaFoto[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    ringkasan: Ringkasan;
    classrooms: Opsi[];
    filters: { kelas: string; q: string };
    schoolId: string | null;
};

const URL_HALAMAN = '/admin/siswa-tanpa-foto';

// Radix Select menolak SelectItem bernilai string kosong; pola yang sama
// dipakai halaman daftar siswa dan Generate Kartu.
const SEMUA = 'all';

function Angka({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="rounded-lg border p-4">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-2xl font-bold tabular-nums">{value}</p>
        </div>
    );
}

export default function SiswaTanpaFotoIndex({
    siswa,
    ringkasan,
    classrooms,
    filters,
    schoolId,
}: PageProps) {
    const { studioUrl } = usePage().props as unknown as {
        studioUrl?: string;
    };
    const [cari, setCari] = useState(filters.q);
    /*
        Indeks baris yang modalnya dibuka; null berarti tertutup. Juga dipakai
        sebagai `key` modal supaya tiap pembukaan menyalin antrean yang segar.
    */
    const [barisFoto, setBarisFoto] = useState<number | null>(null);

    function muat(query: Partial<PageProps['filters']> & { page?: number }) {
        const gabungan = { ...filters, ...query };

        router.get(
            URL_HALAMAN,
            {
                ...(gabungan.kelas ? { kelas: gabungan.kelas } : {}),
                ...(gabungan.q ? { q: gabungan.q } : {}),
                ...(query.page && query.page > 1 ? { page: query.page } : {}),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function kirimCari(e: FormEvent) {
        e.preventDefault();
        muat({ q: cari.trim() });
    }

    const antrean = siswa.data.map((s) => ({
        id: s.id,
        full_name: s.full_name,
        nis: s.nis,
        classroom: s.classroom,
    }));

    return (
        <>
            <Head title="Siswa Belum Ada Foto" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Siswa Belum Ada Foto
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Siswa aktif yang belum punya pas foto, baik di server
                        maupun di Google Drive. Pasang fotonya langsung dari
                        sini tanpa membuka siswa satu per satu.
                    </p>
                </div>

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Angka label="Total siswa aktif" value={ringkasan.total} />
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
                        <CardTitle>Daftar Siswa</CardTitle>
                        <CardDescription>
                            Cari di Drive dan Ambil Ulang Foto tersedia di
                            halaman Detail siswa.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-col gap-3 sm:flex-row">
                            <Select
                                value={filters.kelas || SEMUA}
                                onValueChange={(value) =>
                                    muat({
                                        kelas: value === SEMUA ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger className="w-full sm:w-56">
                                    <SelectValue placeholder="Semua kelas" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={SEMUA}>
                                        Semua kelas
                                    </SelectItem>
                                    {classrooms.map((c) => (
                                        <SelectItem key={c.id} value={c.id}>
                                            {c.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <form
                                onSubmit={kirimCari}
                                className="flex flex-1 gap-2"
                            >
                                <Input
                                    value={cari}
                                    onChange={(e) => setCari(e.target.value)}
                                    placeholder="Cari nama atau NIS"
                                    aria-label="Cari nama atau NIS"
                                />
                                <Button type="submit" variant="outline">
                                    <Search className="size-4" />
                                    Cari
                                </Button>
                            </form>
                        </div>

                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Nama</TableHead>
                                        <TableHead>NIS</TableHead>
                                        <TableHead>Kelas</TableHead>
                                        <TableHead>No. Absen</TableHead>
                                        <TableHead className="text-right">
                                            Aksi
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {siswa.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="py-10 text-center text-muted-foreground"
                                            >
                                                {filters.q || filters.kelas ? (
                                                    'Tidak ada siswa tanpa foto yang cocok dengan filter ini.'
                                                ) : (
                                                    <span className="inline-flex items-center gap-2">
                                                        <CheckCircle2 className="size-4 text-green-600" />
                                                        Semua siswa aktif sudah
                                                        punya pas foto.
                                                    </span>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        siswa.data.map((s, i) => (
                                            <TableRow key={s.id}>
                                                <TableCell className="font-medium">
                                                    <span className="inline-flex items-center gap-2">
                                                        <ImageOff className="size-4 shrink-0 text-muted-foreground" />
                                                        {s.full_name}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="tabular-nums">
                                                    {s.nis ?? '-'}
                                                </TableCell>
                                                <TableCell>
                                                    {s.classroom ?? '-'}
                                                </TableCell>
                                                <TableCell className="tabular-nums">
                                                    {s.no_absen ?? '-'}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex flex-wrap justify-end gap-2">
                                                        <Button
                                                            size="sm"
                                                            onClick={() =>
                                                                setBarisFoto(i)
                                                            }
                                                        >
                                                            <Upload className="size-3.5" />
                                                            Unggah Foto
                                                        </Button>
                                                        {studioUrl && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                asChild
                                                            >
                                                                <a
                                                                    href={`${studioUrl}/sesi?siswa=${s.id}`}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                >
                                                                    <Aperture className="size-3.5" />
                                                                    Foto di
                                                                    Studio
                                                                </a>
                                                            </Button>
                                                        )}
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={`/admin/siswa/${s.id}`}
                                                            >
                                                                <ExternalLink className="size-3.5" />
                                                                Detail
                                                            </Link>
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        {siswa.total > 0 && (
                            <div className="flex flex-col items-center justify-between gap-2 sm:flex-row">
                                <p className="text-xs text-muted-foreground">
                                    {siswa.from}–{siswa.to} dari {siswa.total}{' '}
                                    siswa
                                </p>
                                {siswa.last_page > 1 && (
                                    <div className="flex items-center gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={siswa.current_page <= 1}
                                            onClick={() =>
                                                muat({
                                                    page:
                                                        siswa.current_page - 1,
                                                })
                                            }
                                        >
                                            <ChevronLeft className="size-4" />
                                            Sebelumnya
                                        </Button>
                                        <span className="text-xs text-muted-foreground tabular-nums">
                                            {siswa.current_page} /{' '}
                                            {siswa.last_page}
                                        </span>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={
                                                siswa.current_page >=
                                                siswa.last_page
                                            }
                                            onClick={() =>
                                                muat({
                                                    page:
                                                        siswa.current_page + 1,
                                                })
                                            }
                                        >
                                            Berikutnya
                                            <ChevronRight className="size-4" />
                                        </Button>
                                    </div>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {barisFoto !== null && (
                    <ModalPasFotoAntre
                        key={barisFoto}
                        siswa={antrean}
                        mulaiDari={barisFoto}
                        schoolId={schoolId ?? ''}
                        urlUnggah={(id) => `${URL_HALAMAN}/${id}/foto`}
                        muatUlang={['ringkasan', 'siswa']}
                        onTutup={() => setBarisFoto(null)}
                    />
                )}
            </div>
        </>
    );
}

SiswaTanpaFotoIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Siswa Belum Ada Foto', href: URL_HALAMAN },
    ],
};
