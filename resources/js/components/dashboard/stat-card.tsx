import { TrendingDown, TrendingUp } from 'lucide-react';

type StatCardProps = {
    title: string;
    value: string | number;
    delta?: number | null;
    deltaLabel?: string;
    hint?: string;
    icon: React.ReactNode;
    color?: 'blue' | 'green' | 'amber' | 'red' | 'violet';
};

/**
 * Kartu angka ringkas: latar diwarnai tipis sesuai maknanya, garis aksen di
 * kiri, ikon di pojok — supaya deretan angka di atas terbaca sekilas.
 */
const colorMap = {
    blue: {
        card: 'bg-chart-1/5 border-chart-1/20',
        accent: 'bg-chart-1',
        icon: 'bg-chart-1/10 text-chart-1',
    },
    green: {
        card: 'bg-chart-2/5 border-chart-2/20',
        accent: 'bg-chart-2',
        icon: 'bg-chart-2/10 text-chart-2',
    },
    amber: {
        card: 'bg-chart-3/5 border-chart-3/20',
        accent: 'bg-chart-3',
        icon: 'bg-chart-3/10 text-chart-3',
    },
    red: {
        card: 'bg-chart-5/5 border-chart-5/20',
        accent: 'bg-chart-5',
        icon: 'bg-chart-5/10 text-chart-5',
    },
    violet: {
        card: 'bg-chart-4/5 border-chart-4/20',
        accent: 'bg-chart-4',
        icon: 'bg-chart-4/10 text-chart-4',
    },
};

export function StatCard({
    title,
    value,
    delta,
    deltaLabel,
    hint,
    icon,
    color = 'blue',
}: StatCardProps) {
    const c = colorMap[color];

    return (
        <div
            className={`relative overflow-hidden rounded-xl border p-4 ${c.card}`}
        >
            <span
                className={`absolute inset-y-3 left-0 w-1 rounded-r-full ${c.accent}`}
                aria-hidden
            />
            <div className="flex items-start justify-between gap-3 pl-2">
                <p className="text-xs font-medium text-muted-foreground">
                    {title}
                </p>
                <div
                    className={`flex size-8 shrink-0 items-center justify-center rounded-lg [&_svg]:size-4 ${c.icon}`}
                >
                    {icon}
                </div>
            </div>
            <p className="pl-2 text-2xl font-bold tracking-tight text-foreground tabular-nums">
                {value}
            </p>
            {delta !== undefined && delta !== null ? (
                <p className="mt-1 flex items-center gap-1 pl-2 text-xs">
                    {delta > 0 ? (
                        <TrendingUp className="size-3 text-chart-2" />
                    ) : delta < 0 ? (
                        <TrendingDown className="size-3 text-chart-5" />
                    ) : null}
                    <span
                        className={
                            delta > 0
                                ? 'font-medium text-chart-2'
                                : delta < 0
                                  ? 'font-medium text-chart-5'
                                  : 'text-muted-foreground'
                        }
                    >
                        {delta > 0 ? '+' : ''}
                        {delta}%
                    </span>
                    {deltaLabel && (
                        <span className="text-muted-foreground">
                            {deltaLabel}
                        </span>
                    )}
                </p>
            ) : (
                hint && (
                    <p className="mt-1 pl-2 text-xs text-muted-foreground">
                        {hint}
                    </p>
                )
            )}
        </div>
    );
}
