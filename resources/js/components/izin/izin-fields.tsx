import { Camera, HeartPulse, ImageUp, NotebookPen, X } from 'lucide-react';
import { useEffect, useMemo } from 'react';
import { cn } from '@/lib/utils';

export type JenisIzin = 'IZIN' | 'SAKIT';

export const LABEL_STATUS_IZIN: Record<string, string> = {
    menunggu: 'Menunggu tinjauan',
    disetujui: 'Disetujui',
    ditolak: 'Ditolak',
};

export const WARNA_STATUS_IZIN: Record<string, string> = {
    menunggu:
        'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200',
    disetujui:
        'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
    ditolak:
        'border-red-300 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200',
};

/** Dua kartu pilihan besar: Sakit atau Izin. */
export function PilihanJenis({
    nilai,
    onUbah,
}: {
    nilai: JenisIzin | '';
    onUbah: (jenis: JenisIzin) => void;
}) {
    const opsi: {
        jenis: JenisIzin;
        judul: string;
        ket: string;
        ikon: typeof HeartPulse;
    }[] = [
        {
            jenis: 'SAKIT',
            judul: 'Sakit',
            ket: 'Anak sedang sakit atau berobat',
            ikon: HeartPulse,
        },
        {
            jenis: 'IZIN',
            judul: 'Izin',
            ket: 'Keperluan keluarga atau lainnya',
            ikon: NotebookPen,
        },
    ];

    return (
        <div
            role="radiogroup"
            aria-label="Jenis"
            className="grid grid-cols-2 gap-3"
        >
            {opsi.map(({ jenis, judul, ket, ikon: Ikon }) => {
                const aktif = nilai === jenis;

                return (
                    <button
                        key={jenis}
                        type="button"
                        role="radio"
                        aria-checked={aktif}
                        onClick={() => onUbah(jenis)}
                        className={cn(
                            'flex flex-col items-start gap-2 rounded-xl border p-4 text-left transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            aktif
                                ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                : 'hover:bg-muted/60',
                        )}
                    >
                        <Ikon
                            className={cn(
                                'size-5',
                                aktif
                                    ? 'text-primary'
                                    : 'text-muted-foreground',
                            )}
                        />
                        <span className="font-semibold">{judul}</span>
                        <span className="text-xs text-muted-foreground">
                            {ket}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}

/**
 * Unggah foto bukti (surat dokter, undangan, dll.) dengan pratinjau. Server
 * mengubahnya ke webp supaya ringan; di HP, tombol kamera langsung memotret.
 */
export function BuktiFoto({
    berkas,
    onUbah,
}: {
    berkas: File | null;
    onUbah: (berkas: File | null) => void;
}) {
    const pratinjau = useMemo(() => (berkas ? URL.createObjectURL(berkas) : null), [berkas]);

    useEffect(
        () => () => {
            if (pratinjau) {
                URL.revokeObjectURL(pratinjau);
            }
        },
        [pratinjau],
    );

    if (berkas && pratinjau) {
        return (
            <div className="relative overflow-hidden rounded-xl border">
                <img
                    src={pratinjau}
                    alt="Pratinjau bukti"
                    className="max-h-72 w-full bg-muted object-contain"
                />
                <button
                    type="button"
                    onClick={() => onUbah(null)}
                    className="absolute top-2 right-2 flex size-8 items-center justify-center rounded-full bg-black/60 text-white hover:bg-black/80"
                    aria-label="Hapus foto bukti"
                >
                    <X className="size-4" />
                </button>
                <p className="truncate border-t bg-card px-3 py-2 text-xs text-muted-foreground">
                    {berkas.name}
                </p>
            </div>
        );
    }

    return (
        <div className="grid grid-cols-2 gap-3">
            <label className="flex cursor-pointer flex-col items-center gap-2 rounded-xl border border-dashed p-5 text-center text-sm hover:bg-muted/60">
                <ImageUp className="size-5 text-muted-foreground" />
                Pilih foto
                <input
                    type="file"
                    accept="image/*"
                    className="sr-only"
                    onChange={(e) => onUbah(e.target.files?.[0] ?? null)}
                />
            </label>
            <label className="flex cursor-pointer flex-col items-center gap-2 rounded-xl border border-dashed p-5 text-center text-sm hover:bg-muted/60">
                <Camera className="size-5 text-muted-foreground" />
                Foto langsung
                <input
                    type="file"
                    accept="image/*"
                    capture="environment"
                    className="sr-only"
                    onChange={(e) => onUbah(e.target.files?.[0] ?? null)}
                />
            </label>
        </div>
    );
}

export function rentangTanggalIzin(mulai: string, selesai: string): string {
    const f = (t: string) =>
        new Intl.DateTimeFormat('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        }).format(new Date(`${t}T00:00:00`));

    return mulai === selesai ? f(mulai) : `${f(mulai)} – ${f(selesai)}`;
}
