import { TrendingDown, TrendingUp } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type StatCardProps = {
    title: string;
    value: string | number;
    delta?: number | null;
    deltaLabel?: string;
    icon: React.ReactNode;
    variant?: 'default' | 'warning' | 'destructive';
};

export function StatCard({ title, value, delta, deltaLabel, icon, variant = 'default' }: StatCardProps) {
    const variantStyles = {
        default: 'text-foreground',
        warning: 'text-chart-3',
        destructive: 'text-destructive',
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
                <CardTitle className="text-muted-foreground text-sm font-medium">{title}</CardTitle>
                <div className="text-muted-foreground">{icon}</div>
            </CardHeader>
            <CardContent>
                <div className={`text-2xl font-bold ${variantStyles[variant]}`}>{value}</div>
                {delta !== undefined && delta !== null && (
                    <p className="text-muted-foreground mt-1 flex items-center gap-1 text-xs">
                        {delta > 0 ? (
                            <TrendingUp className="size-3 text-emerald-500" />
                        ) : delta < 0 ? (
                            <TrendingDown className="size-3 text-red-500" />
                        ) : null}
                        <span className={delta > 0 ? 'text-emerald-500' : delta < 0 ? 'text-red-500' : ''}>
                            {delta > 0 ? '+' : ''}
                            {delta}%
                        </span>
                        {deltaLabel && <span>{deltaLabel}</span>}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
