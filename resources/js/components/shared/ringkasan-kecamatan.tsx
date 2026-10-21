import { cn } from '@/lib/utils';

export type BarisKecamatan = {
    kecamatan: string;
    peserta: number;
    [kolom: string]: string | number;
};

/**
 * Tabel rekap per kecamatan. Baris bisa diklik untuk memfilter laporan ke
 * kecamatan itu; baris aktif ditandai.
 */
export function RingkasanKecamatan({
    baris,
    kolom,
    aktif,
    onPilih,
}: {
    baris: BarisKecamatan[];
    kolom: { key: string; label: string }[];
    aktif?: string;
    onPilih?: (kecamatan: string) => void;
}) {
    if (baris.length === 0) {
        return null;
    }

    return (
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full text-sm">
                <thead className="bg-muted/50 text-left">
                    <tr>
                        <th className="p-2.5 font-medium">Kecamatan</th>
                        <th className="p-2.5 text-right font-medium">
                            Peserta
                        </th>
                        {kolom.map((k) => (
                            <th
                                key={k.key}
                                className="p-2.5 text-right font-medium"
                            >
                                {k.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {baris.map((b) => (
                        <tr
                            key={b.kecamatan}
                            onClick={() => onPilih?.(b.kecamatan)}
                            className={cn(
                                'border-t',
                                onPilih && 'cursor-pointer hover:bg-muted/40',
                                aktif === b.kecamatan && 'bg-primary/10',
                            )}
                        >
                            <td className="p-2.5 font-medium">{b.kecamatan}</td>
                            <td className="p-2.5 text-right tabular-nums">
                                {b.peserta}
                            </td>
                            {kolom.map((k) => (
                                <td
                                    key={k.key}
                                    className="p-2.5 text-right tabular-nums"
                                >
                                    {b[k.key] ?? 0}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
