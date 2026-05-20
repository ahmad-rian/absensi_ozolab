import { TrendingDown, TrendingUp } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

type StatCardProps = {
    title: string;
    value: string | number;
    delta?: number | null;
    deltaLabel?: string;
    icon: React.ReactNode;
    color?: 'blue' | 'green' | 'amber' | 'red';
    variant?: 'default' | 'warning' | 'destructive';
};

const colorMap = {
    blue: { bg: 'bg-chart-1/10', text: 'text-chart-1', ring: 'ring-chart-1/20' },
    green: { bg: 'bg-chart-2/10', text: 'text-chart-2', ring: 'ring-chart-2/20' },
    amber: { bg: 'bg-chart-3/10', text: 'text-chart-3', ring: 'ring-chart-3/20' },
    red: { bg: 'bg-chart-5/10', text: 'text-chart-5', ring: 'ring-chart-5/20' },
};

export function StatCard({ title, value, delta, deltaLabel, icon, color = 'blue', variant = 'default' }: StatCardProps) {
    const c = colorMap[color];

    return (
        <div className="bg-card border-border flex items-start gap-4 rounded-2xl border p-5">
            <div className={`flex size-11 shrink-0 items-center justify-center rounded-xl ${c.bg} ring-1 ${c.ring}`}>
                <div className={c.text}>{icon}</div>
            </div>
            <div className="min-w-0 flex-1">
                <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{title}</p>
                <p className="text-foreground mt-1 text-2xl font-bold tracking-tight">{value}</p>
                {delta !== undefined && delta !== null && (
                    <p className="mt-1 flex items-center gap-1 text-xs">
                        {delta > 0 ? (
                            <TrendingUp className="size-3 text-chart-2" />
                        ) : delta < 0 ? (
                            <TrendingDown className="size-3 text-chart-5" />
                        ) : null}
                        <span className={delta > 0 ? 'text-chart-2 font-medium' : delta < 0 ? 'text-chart-5 font-medium' : 'text-muted-foreground'}>
                            {delta > 0 ? '+' : ''}{delta}%
                        </span>
                        {deltaLabel && <span className="text-muted-foreground">{deltaLabel}</span>}
                    </p>
                )}
            </div>
        </div>
    );
}
