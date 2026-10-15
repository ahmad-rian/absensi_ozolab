import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

export type RiwayatKehadiranRow = {
    id: string;
    tanggal: string;
    hari: string;
    sesi: string;
    status: string;
    status_label: string;
    jam_datang: string | null;
    jam_pulang: string | null;
    sumber: string;
    catatan: string | null;
};

export type StatistikKehadiran = {
    total: number;
    counts: Record<string, number>;
    persen_hadir: number;
    rata_datang: string | null;
    paling_awal: string | null;
    paling_akhir: string | null;
    rata_terlambat_menit: number | null;
};

export type HalamanRiwayat = {
    data: RiwayatKehadiranRow[];
    current_page: number;
    last_page: number;
    total: number;
};

const WARNA: Record<string, string> = {
    HADIR: 'border-emerald-300 text-emerald-700 dark:border-emerald-800 dark:text-emerald-300',
    TERLAMBAT:
        'border-amber-300 text-amber-700 dark:border-amber-800 dark:text-amber-300',
    IZIN: 'border-sky-300 text-sky-700 dark:border-sky-800 dark:text-sky-300',
    SAKIT: 'border-violet-300 text-violet-700 dark:border-violet-800 dark:text-violet-300',
    ALPA: 'border-red-300 text-red-700 dark:border-red-800 dark:text-red-300',
};

function Angka({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 font-mono text-lg font-semibold tabular-nums">
                {value}
            </p>
        </div>
    );
}

/**
 * Statistik dan riwayat kehadiran satu peserta, padanan riwayat absensi di
 * detail siswa. Data dikirim server sebagai deferred prop; `undefined` berarti
 * masih dimuat.
 */
export function RiwayatKehadiranCard({
    statistik,
    halaman,
    onPage,
}: {
    statistik?: StatistikKehadiran | null;
    halaman?: HalamanRiwayat | null;
    onPage: (page: number) => void;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Riwayat kehadiran</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-4">
                {!statistik ? (
                    <Skeleton className="h-20 w-full" />
                ) : (
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <Angka
                            label="Kehadiran"
                            value={`${statistik.persen_hadir}%`}
                        />
                        <Angka
                            label="Rata-rata datang"
                            value={statistik.rata_datang ?? '–'}
                        />
                        <Angka
                            label="Paling awal / akhir"
                            value={
                                statistik.paling_awal
                                    ? `${statistik.paling_awal} / ${statistik.paling_akhir}`
                                    : '–'
                            }
                        />
                        <Angka
                            label="Rata-rata terlambat"
                            value={
                                statistik.rata_terlambat_menit !== null
                                    ? `${statistik.rata_terlambat_menit} menit`
                                    : '–'
                            }
                        />
                        <p className="col-span-full text-xs text-muted-foreground">
                            {Object.entries(statistik.counts)
                                .filter(([, n]) => n > 0)
                                .map(
                                    ([s, n]) =>
                                        `${s.charAt(0)}${s.slice(1).toLowerCase()} ${n}`,
                                )
                                .join(' · ') || 'Belum ada catatan kehadiran.'}
                        </p>
                    </div>
                )}

                {!halaman ? (
                    <Skeleton className="h-32 w-full" />
                ) : halaman.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Belum ada catatan kehadiran.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="p-2.5 font-medium">
                                        Tanggal
                                    </th>
                                    <th className="p-2.5 font-medium">Sesi</th>
                                    <th className="p-2.5 font-medium">
                                        Status
                                    </th>
                                    <th className="p-2.5 font-medium">
                                        Datang
                                    </th>
                                    <th className="p-2.5 font-medium">
                                        Pulang
                                    </th>
                                    <th className="p-2.5 font-medium">
                                        Catatan
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {halaman.data.map((r) => (
                                    <tr key={r.id} className="border-t">
                                        <td className="p-2.5 whitespace-nowrap">
                                            <span className="font-medium tabular-nums">
                                                {r.tanggal}
                                            </span>
                                            <span className="block text-xs text-muted-foreground capitalize">
                                                {r.hari}
                                            </span>
                                        </td>
                                        <td className="p-2.5">{r.sesi}</td>
                                        <td className="p-2.5">
                                            <Badge
                                                variant="outline"
                                                className={WARNA[r.status]}
                                            >
                                                {r.status_label}
                                            </Badge>
                                            <span className="block text-xs text-muted-foreground">
                                                {r.sumber}
                                            </span>
                                        </td>
                                        <td className="p-2.5 font-mono tabular-nums">
                                            {r.jam_datang ?? '–'}
                                        </td>
                                        <td className="p-2.5 font-mono tabular-nums">
                                            {r.jam_pulang ?? '–'}
                                        </td>
                                        <td className="max-w-56 p-2.5 text-muted-foreground">
                                            {r.catatan ?? '–'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {halaman && halaman.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Halaman {halaman.current_page} dari{' '}
                            {halaman.last_page} · {halaman.total} catatan
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={halaman.current_page <= 1}
                                onClick={() => onPage(halaman.current_page - 1)}
                            >
                                Sebelumnya
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={
                                    halaman.current_page >= halaman.last_page
                                }
                                onClick={() => onPage(halaman.current_page + 1)}
                            >
                                Berikutnya
                            </Button>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
