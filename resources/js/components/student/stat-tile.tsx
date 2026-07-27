import type { LucideIcon } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

const toneMap = {
    blue: 'bg-chart-1/10 text-chart-1 ring-chart-1/20',
    green: 'bg-chart-2/10 text-chart-2 ring-chart-2/20',
    amber: 'bg-chart-3/10 text-chart-3 ring-chart-3/20',
    red: 'bg-chart-5/10 text-chart-5 ring-chart-5/20',
    slate: 'bg-muted text-muted-foreground ring-border',
} as const;

export type StatTone = keyof typeof toneMap;

export function StatTile({
    label,
    value,
    suffix,
    hint,
    icon: Icon,
    tone = 'blue',
}: {
    label: string;
    value: number | string;
    suffix?: string;
    hint?: string;
    icon: LucideIcon;
    tone?: StatTone;
}) {
    return (
        <Card>
            <CardContent className="flex items-center gap-4 p-4">
                <div className={cn('flex size-10 shrink-0 items-center justify-center rounded-xl ring-1', toneMap[tone])}>
                    <Icon className="size-5" />
                </div>
                <div className="min-w-0">
                    <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{label}</p>
                    <p className="text-2xl font-bold tabular-nums">
                        {value}
                        {suffix && <span className="text-muted-foreground ml-0.5 text-base font-semibold">{suffix}</span>}
                    </p>
                    {hint && <p className="text-muted-foreground truncate text-xs">{hint}</p>}
                </div>
            </CardContent>
        </Card>
    );
}
