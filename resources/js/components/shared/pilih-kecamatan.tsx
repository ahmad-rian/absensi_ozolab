import { cn } from '@/lib/utils';
import data from '../../../data/kecamatan_barlingmascakep.json';

type DataKecamatan = {
    kabupaten: { nama: string; kecamatan: { kode: string; nama: string }[] }[];
};

const KABUPATEN = (data as DataKecamatan).kabupaten;

/** Nilai yang disimpan: "Kecamatan, Kabupaten" (ada nama kembar antar kabupaten). */
export const nilaiKecamatan = (kecamatan: string, kabupaten: string) =>
    `${kecamatan}, ${kabupaten}`;

/**
 * Pilihan kecamatan Barlingmascakep, dikelompokkan per kabupaten. Memakai
 * `<select>` bawaan supaya di HP muncul pemilih sistem yang mudah digulir.
 */
export function PilihKecamatan({
    id,
    nilai,
    onUbah,
    wajib,
    name,
    className,
}: {
    id?: string;
    nilai: string;
    onUbah?: (nilai: string) => void;
    wajib?: boolean;
    name?: string;
    className?: string;
}) {
    return (
        <select
            id={id}
            name={name}
            required={wajib}
            value={nilai}
            onChange={(e) => onUbah?.(e.target.value)}
            className={cn(
                'h-9 w-full rounded-md border bg-background px-2 text-sm',
                className,
            )}
        >
            <option value="">Pilih kecamatan…</option>
            {KABUPATEN.map((kab) => (
                <optgroup key={kab.nama} label={`Kab. ${kab.nama}`}>
                    {kab.kecamatan.map((kec) => {
                        const v = nilaiKecamatan(kec.nama, kab.nama);

                        return (
                            <option key={kec.kode} value={v}>
                                {kec.nama}
                            </option>
                        );
                    })}
                </optgroup>
            ))}
        </select>
    );
}
