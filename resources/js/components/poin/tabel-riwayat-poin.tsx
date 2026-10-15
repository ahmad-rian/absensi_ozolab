import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { destroy } from '@/routes/admin/poin';

export type BarisPoin = {
    id: string;
    tanggal: string;
    sumber: 'TERLAMBAT' | 'ALPA' | 'MANUAL';
    label: string;
    poin: number;
    alasan: string | null;
    pencatat: string | null;
    siswa: {
        id: string | null;
        nama: string | null;
        nis: string | null;
        kelas: string | null;
    } | null;
};

const WARNA_SUMBER: Record<BarisPoin['sumber'], string> = {
    TERLAMBAT:
        'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    ALPA: 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
    MANUAL: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200',
};

const tanggalPendek = (iso: string) =>
    new Intl.DateTimeFormat('id-ID', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${iso}T00:00:00`));

/** Daftar catatan poin; dipakai halaman Riwayat dan tab Poin di detail siswa. */
export function TabelRiwayatPoin({
    baris,
    tampilSiswa,
    bolehHapus,
    reloadOnly,
}: {
    baris: BarisPoin[];
    tampilSiswa: boolean;
    bolehHapus: boolean;
    /** Muat ulang hanya prop ini setelah hapus (tab detail siswa). */
    reloadOnly?: string[];
}) {
    const hapus = (b: BarisPoin) => {
        if (window.confirm(`Hapus catatan "${b.label}" (${b.poin} poin)?`)) {
            router.delete(destroy.url(b.id), {
                preserveScroll: true,
                ...(reloadOnly ? { only: reloadOnly } : {}),
            });
        }
    };

    return (
        <div className="overflow-x-auto rounded-xl border">
            <table className="w-full text-sm">
                <thead className="bg-muted/50 text-left text-xs text-muted-foreground uppercase">
                    <tr>
                        <th className="px-4 py-3 font-medium">Tanggal</th>
                        {tampilSiswa && (
                            <th className="px-4 py-3 font-medium">Siswa</th>
                        )}
                        <th className="px-4 py-3 font-medium">Jenis</th>
                        <th className="px-4 py-3 font-medium">Alasan</th>
                        <th className="px-4 py-3 text-right font-medium">
                            Poin
                        </th>
                        {bolehHapus && <th className="w-12 px-2 py-3" />}
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {baris.length === 0 && (
                        <tr>
                            <td
                                colSpan={
                                    3 + Number(tampilSiswa) + Number(bolehHapus)
                                }
                                className="px-4 py-10 text-center text-muted-foreground"
                            >
                                Belum ada catatan poin.
                            </td>
                        </tr>
                    )}
                    {baris.map((b) => (
                        <tr key={b.id} className="align-top">
                            <td className="px-4 py-3 whitespace-nowrap">
                                {tanggalPendek(b.tanggal)}
                            </td>
                            {tampilSiswa && (
                                <td className="px-4 py-3">
                                    <span className="block font-medium">
                                        {b.siswa?.nama ?? '—'}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {b.siswa?.nis}
                                        {b.siswa?.kelas
                                            ? ` · ${b.siswa.kelas}`
                                            : ''}
                                    </span>
                                </td>
                            )}
                            <td className="px-4 py-3">
                                <span
                                    className={cn(
                                        'rounded-md px-2 py-0.5 text-xs font-medium',
                                        WARNA_SUMBER[b.sumber],
                                    )}
                                >
                                    {b.label}
                                </span>
                                {b.sumber !== 'MANUAL' && (
                                    <span className="mt-1 block text-xs text-muted-foreground">
                                        Otomatis
                                    </span>
                                )}
                            </td>
                            <td className="max-w-md px-4 py-3">
                                <span className="block whitespace-pre-line">
                                    {b.alasan ?? '—'}
                                </span>
                                {b.pencatat && (
                                    <span className="mt-1 block text-xs text-muted-foreground">
                                        Dicatat {b.pencatat}
                                    </span>
                                )}
                            </td>
                            <td className="px-4 py-3 text-right font-semibold tabular-nums">
                                +{b.poin}
                            </td>
                            {bolehHapus && (
                                <td className="px-2 py-2">
                                    {b.sumber === 'MANUAL' && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Hapus catatan"
                                            onClick={() => hapus(b)}
                                        >
                                            <Trash2 className="size-4 text-destructive" />
                                        </Button>
                                    )}
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
