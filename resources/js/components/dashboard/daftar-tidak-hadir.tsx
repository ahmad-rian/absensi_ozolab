import { Link } from '@inertiajs/react';
import { ChevronDown, ClipboardCheck } from 'lucide-react';
import { show as absenKelas } from '@/actions/App/Http/Controllers/Admin/AbsensiKelasController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

export type KeadaanTidakHadir =
    | 'libur'
    | 'belum_ada_scan'
    | 'berjalan'
    | 'tutup';

export type TidakHadir = {
    keadaan: KeadaanTidakHadir;
    jumlah: number | null;
    kelas: {
        id: string | null;
        nama: string;
        siswa: { id: string; nama: string; nis: string | null }[];
    }[];
};

/** Judul kartu stat: sebelum jam masuk tutup, mereka "belum" hadir, bukan "tidak". */
export function judulTidakHadir(data: TidakHadir | null | undefined): string {
    return data?.keadaan === 'berjalan' ? 'Belum Hadir' : 'Tidak Hadir';
}

/** Nilai kartu stat; libur / belum ada scan tidak diberi angka supaya tidak menyesatkan. */
export function nilaiTidakHadir(
    data: TidakHadir | null | undefined,
): string | number {
    if (data === undefined) {
        return '…';
    }

    if (data === null || data.jumlah === null) {
        return '-';
    }

    return data.jumlah;
}

const KETERANGAN: Record<KeadaanTidakHadir, string> = {
    libur: 'Hari ini tidak ada jadwal absensi.',
    belum_ada_scan:
        'Belum ada siswa yang absen masuk hari ini. Daftar muncul setelah scan pertama.',
    berjalan:
        'Jam masuk belum tutup. Daftar ini siswa yang BELUM absen masuk sejauh ini.',
    tutup: 'Jam masuk sudah tutup. Siswa izin dan sakit tidak termasuk.',
};

export function DaftarTidakHadir({ data }: { data?: TidakHadir | null }) {
    if (data === undefined) {
        return (
            <Card>
                <CardHeader>
                    <Skeleton className="h-5 w-48" />
                    <Skeleton className="h-4 w-72" />
                </CardHeader>
                <CardContent className="space-y-2">
                    {[0, 1, 2].map((i) => (
                        <Skeleton key={i} className="h-10 w-full" />
                    ))}
                </CardContent>
            </Card>
        );
    }

    if (data === null) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>
                    {judulTidakHadir(data)} Hari Ini
                    {data.jumlah !== null && (
                        <Badge
                            variant="secondary"
                            className="ml-2 align-middle tabular-nums"
                        >
                            {data.jumlah}
                        </Badge>
                    )}
                </CardTitle>
                <CardDescription>{KETERANGAN[data.keadaan]}</CardDescription>
            </CardHeader>
            <CardContent>
                {data.kelas.length === 0 ? (
                    <p className="py-6 text-center text-sm text-muted-foreground">
                        {data.jumlah === 0
                            ? 'Semua siswa sudah absen masuk.'
                            : 'Tidak ada data untuk ditampilkan.'}
                    </p>
                ) : (
                    <ul className="max-h-[420px] divide-y divide-border overflow-y-auto rounded-xl border">
                        {data.kelas.map((kelas) => (
                            <li key={kelas.id ?? 'tanpa-kelas'}>
                                <details className="group">
                                    <summary className="flex cursor-pointer list-none items-center gap-3 px-4 py-3 hover:bg-muted/50">
                                        <ChevronDown className="size-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-180" />
                                        <span className="flex-1 font-medium">
                                            {kelas.nama}
                                        </span>
                                        <Badge
                                            variant="outline"
                                            className="tabular-nums"
                                        >
                                            {kelas.siswa.length} siswa
                                        </Badge>
                                    </summary>
                                    <div className="space-y-3 px-4 pb-4">
                                        <ol className="grid gap-1 text-sm">
                                            {kelas.siswa.map((s) => (
                                                <li
                                                    key={s.id}
                                                    className="flex gap-2"
                                                >
                                                    <span className="truncate">
                                                        {s.nama}
                                                    </span>
                                                    {s.nis && (
                                                        <span className="text-muted-foreground tabular-nums">
                                                            {s.nis}
                                                        </span>
                                                    )}
                                                </li>
                                            ))}
                                        </ol>
                                        {kelas.id && (
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link
                                                    href={absenKelas.url(
                                                        kelas.id,
                                                    )}
                                                >
                                                    <ClipboardCheck className="mr-2 size-4" />{' '}
                                                    Absen kelas ini
                                                </Link>
                                            </Button>
                                        )}
                                    </div>
                                </details>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
