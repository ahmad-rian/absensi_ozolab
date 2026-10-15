import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { TabelRiwayatPoin } from '@/components/poin/tabel-riwayat-poin';
import type { BarisPoin } from '@/components/poin/tabel-riwayat-poin';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import { index, riwayat } from '@/routes/admin/poin';

type Props = {
    poin: {
        data: BarisPoin[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    classrooms: { id: string; name: string }[];
    filters: {
        sumber: string;
        kelas: string;
        q: string;
        dari: string;
        sampai: string;
    };
    bolehKelola: boolean;
};

/** Semua catatan poin, terbaru dulu. */
export default function PoinRiwayat({
    poin,
    classrooms,
    filters,
    bolehKelola,
}: Props) {
    const muat = (patch: Partial<Props['filters']>) =>
        router.get(
            riwayat.url(),
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Riwayat Poin" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Riwayat Poin
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Poin otomatis hilang sendiri bila absensinya dikoreksi
                        (misalnya izin disetujui). Yang bisa dihapus manual
                        hanya catatan guru BK.
                    </p>
                </div>

                <div className="flex flex-wrap gap-2">
                    <select
                        aria-label="Jenis"
                        className="h-9 rounded-md border bg-background px-2 text-sm"
                        value={filters.sumber}
                        onChange={(e) => muat({ sumber: e.target.value })}
                    >
                        <option value="">Semua jenis</option>
                        <option value="TERLAMBAT">Terlambat</option>
                        <option value="ALPA">Tidak hadir</option>
                        <option value="MANUAL">Pelanggaran</option>
                    </select>
                    <select
                        aria-label="Kelas"
                        className="h-9 rounded-md border bg-background px-2 text-sm"
                        value={filters.kelas}
                        onChange={(e) => muat({ kelas: e.target.value })}
                    >
                        <option value="">Semua kelas</option>
                        {classrooms.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </select>
                    <Input
                        type="date"
                        aria-label="Dari"
                        className="w-auto"
                        value={filters.dari}
                        onChange={(e) => muat({ dari: e.target.value })}
                    />
                    <Input
                        type="date"
                        aria-label="Sampai"
                        className="w-auto"
                        value={filters.sampai}
                        onChange={(e) => muat({ sampai: e.target.value })}
                    />
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            className="pl-9"
                            placeholder="Cari nama siswa…"
                            defaultValue={filters.q}
                            onKeyDown={(e) =>
                                e.key === 'Enter' &&
                                muat({ q: e.currentTarget.value })
                            }
                        />
                    </div>
                </div>

                <TabelRiwayatPoin
                    baris={poin.data}
                    tampilSiswa
                    bolehHapus={bolehKelola}
                />

                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span>{poin.total} catatan</span>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!poin.prev_page_url}
                            onClick={() =>
                                poin.prev_page_url &&
                                router.get(poin.prev_page_url)
                            }
                        >
                            Sebelumnya
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!poin.next_page_url}
                            onClick={() =>
                                poin.next_page_url &&
                                router.get(poin.next_page_url)
                            }
                        >
                            Berikutnya
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}

PoinRiwayat.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Poin Siswa', href: index() },
        { title: 'Riwayat', href: riwayat() },
    ],
};
