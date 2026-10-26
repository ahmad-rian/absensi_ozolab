import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { show } from '@/actions/App/Http/Controllers/Admin/AbsensiKelasController';
import type { RekapBulanan } from '@/components/absensi-kelas/types';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

const WARNA: Record<string, string> = {
    H: 'bg-green-600/15 text-green-700 dark:text-green-400',
    T: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
    I: 'bg-blue-600/15 text-blue-700 dark:text-blue-400',
    S: 'bg-purple-600/15 text-purple-700 dark:text-purple-400',
    A: 'bg-red-600/15 text-red-700 dark:text-red-400',
};

const NAMA_BULAN = new Intl.DateTimeFormat('id-ID', {
    month: 'long',
    year: 'numeric',
});

function geserBulan(bulan: string, langkah: number): string {
    const [tahun, bln] = bulan.split('-').map(Number);
    const tanggal = new Date(tahun, bln - 1 + langkah, 1);

    return `${tanggal.getFullYear()}-${String(tanggal.getMonth() + 1).padStart(2, '0')}`;
}

type Props = {
    kelasId: string;
    bulan: string;
    bulanIni: string;
    rekap?: RekapBulanan;
};

/**
 * Tab "Rekap Bulanan": daftar hadir siswa × hari sekolah. Klik sel untuk
 * membuka tab Absen pada tanggal itu (mis. mengoreksi alpa jadi sakit).
 */
export function RekapBulananKelas({ kelasId, bulan, bulanIni, rekap }: Props) {
    const [tahun, bln] = bulan.split('-').map(Number);

    function keBulan(tujuan: string) {
        router.get(
            show.url(kelasId, { query: { bulan: tujuan, tab: 'rekap' } }),
            {},
            { preserveScroll: true },
        );
    }

    function bukaTanggal(tanggal: string) {
        router.get(show.url(kelasId, { query: { tanggal, tab: 'absen' } }));
    }

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between gap-3">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => keBulan(geserBulan(bulan, -1))}
                >
                    <ChevronLeft className="size-4" /> Sebelumnya
                </Button>
                <p className="font-semibold capitalize">
                    {NAMA_BULAN.format(new Date(tahun, bln - 1, 1))}
                </p>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={bulan >= bulanIni}
                    onClick={() => keBulan(geserBulan(bulan, 1))}
                >
                    Berikutnya <ChevronRight className="size-4" />
                </Button>
            </div>

            <div className="flex flex-wrap gap-3 text-xs text-muted-foreground">
                {Object.entries({
                    H: 'Hadir',
                    T: 'Terlambat',
                    I: 'Izin',
                    S: 'Sakit',
                    A: 'Alpa',
                }).map(([huruf, label]) => (
                    <span key={huruf} className="flex items-center gap-1.5">
                        <span
                            className={cn(
                                'inline-flex size-5 items-center justify-center rounded font-semibold',
                                WARNA[huruf],
                            )}
                        >
                            {huruf}
                        </span>
                        {label}
                    </span>
                ))}
                <span>· kosong = tidak ada catatan</span>
            </div>

            {!rekap ? (
                <Skeleton className="h-80 w-full" />
            ) : rekap.baris.length === 0 ? (
                <p className="py-10 text-center text-sm text-muted-foreground">
                    Kelas ini belum punya siswa aktif.
                </p>
            ) : (
                <div className="overflow-x-auto rounded-xl border bg-card">
                    <table className="w-full border-collapse text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50">
                                <th className="sticky left-0 z-10 min-w-48 bg-muted px-3 py-2 text-left font-medium">
                                    Siswa
                                </th>
                                {rekap.hari.map((h) => (
                                    <th
                                        key={h.tanggal}
                                        className="min-w-9 px-1 py-2 text-center text-xs font-medium"
                                    >
                                        <span className="block text-muted-foreground">
                                            {h.nama}
                                        </span>
                                        {h.tgl}
                                    </th>
                                ))}
                                {(['H', 'T', 'I', 'S', 'A'] as const).map(
                                    (huruf) => (
                                        <th
                                            key={huruf}
                                            className="border-l px-2 py-2 text-center text-xs font-medium"
                                        >
                                            {huruf}
                                        </th>
                                    ),
                                )}
                                <th className="border-l px-3 py-2 text-right text-xs font-medium">
                                    % Hadir
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rekap.baris.map((b, i) => (
                                <tr
                                    key={b.id}
                                    className="border-b last:border-0 hover:bg-muted/30"
                                >
                                    <td className="sticky left-0 z-10 bg-card px-3 py-1.5">
                                        <span className="mr-2 text-muted-foreground tabular-nums">
                                            {i + 1}.
                                        </span>
                                        {b.nama}
                                    </td>
                                    {rekap.hari.map((h) => {
                                        const huruf = b.status[h.tanggal];

                                        return (
                                            <td
                                                key={h.tanggal}
                                                className="p-0.5 text-center"
                                            >
                                                <button
                                                    type="button"
                                                    disabled={!h.lewat}
                                                    title={`${b.nama} — ${h.tanggal}`}
                                                    onClick={() =>
                                                        bukaTanggal(h.tanggal)
                                                    }
                                                    className={cn(
                                                        'inline-flex size-7 items-center justify-center rounded text-xs font-semibold focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                                        huruf
                                                            ? WARNA[huruf]
                                                            : h.lewat
                                                              ? 'text-muted-foreground/40 hover:bg-muted'
                                                              : 'opacity-30',
                                                    )}
                                                >
                                                    {huruf ?? '·'}
                                                </button>
                                            </td>
                                        );
                                    })}
                                    {(['H', 'T', 'I', 'S', 'A'] as const).map(
                                        (huruf) => (
                                            <td
                                                key={huruf}
                                                className="border-l px-2 text-center tabular-nums"
                                            >
                                                {b.jumlah[huruf]}
                                            </td>
                                        ),
                                    )}
                                    <td className="border-l px-3 text-right font-medium tabular-nums">
                                        {b.persen === null
                                            ? '-'
                                            : `${b.persen}%`}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
