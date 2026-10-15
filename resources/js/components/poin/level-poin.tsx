import { cn } from '@/lib/utils';

export type LevelPoin = 'kuning' | 'orange' | 'merah' | null;

export type AmbangLevel = { kuning: number; orange: number; merah: number };

/** Warna level poin, dipakai rekap admin, detail siswa, dan portal ortu. */
export const GAYA_LEVEL: Record<
    'aman' | 'kuning' | 'orange' | 'merah',
    { label: string; pill: string; titik: string }
> = {
    aman: {
        label: 'Aman',
        pill: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-300',
        titik: 'bg-emerald-500',
    },
    kuning: {
        label: 'Kuning',
        pill: 'border-yellow-300 bg-yellow-50 text-yellow-800 dark:border-yellow-800 dark:bg-yellow-950/60 dark:text-yellow-300',
        titik: 'bg-yellow-400',
    },
    orange: {
        label: 'Orange',
        pill: 'border-orange-300 bg-orange-50 text-orange-700 dark:border-orange-800 dark:bg-orange-950/60 dark:text-orange-300',
        titik: 'bg-orange-500',
    },
    merah: {
        label: 'Merah',
        pill: 'border-red-300 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-950/60 dark:text-red-300',
        titik: 'bg-red-600',
    },
};

export function LevelPill({
    level,
    className,
}: {
    level: LevelPoin;
    className?: string;
}) {
    const gaya = GAYA_LEVEL[level ?? 'aman'];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-semibold',
                gaya.pill,
                className,
            )}
        >
            <span className={cn('size-1.5 rounded-full', gaya.titik)} />
            {gaya.label}
        </span>
    );
}

export const LABEL_SUMBER: Record<string, string> = {
    TERLAMBAT: 'Terlambat',
    ALPA: 'Tidak hadir',
    MANUAL: 'Pelanggaran',
};

/** Ringkasan total + level + jarak ke level berikutnya. */
export function KartuTotalPoin({
    total,
    level,
    ambang,
    semesterMulai,
}: {
    total: number;
    level: LevelPoin;
    ambang: AmbangLevel;
    semesterMulai: string;
}) {
    const berikut = (['kuning', 'orange', 'merah'] as const).find(
        (l) => total < ambang[l],
    );
    const persen = Math.min(
        100,
        Math.round((total / Math.max(1, ambang.merah)) * 100),
    );

    return (
        <div className="rounded-xl border bg-card p-4">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Poin semester ini
                    </p>
                    <p className="mt-1 text-3xl font-bold tabular-nums">
                        {total}
                    </p>
                </div>
                <LevelPill level={level} />
            </div>
            <div className="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                <div
                    className={cn(
                        'h-full rounded-full transition-[width]',
                        GAYA_LEVEL[level ?? 'aman'].titik,
                    )}
                    style={{ width: `${persen}%` }}
                />
            </div>
            <p className="mt-2 text-xs text-muted-foreground">
                {berikut
                    ? `${ambang[berikut] - total} poin lagi menuju level ${GAYA_LEVEL[berikut].label.toLowerCase()}.`
                    : 'Sudah di level tertinggi.'}{' '}
                Dihitung sejak{' '}
                {new Intl.DateTimeFormat('id-ID', { dateStyle: 'long' }).format(
                    new Date(`${semesterMulai}T00:00:00`),
                )}
                .
            </p>
        </div>
    );
}
