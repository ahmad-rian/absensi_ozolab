import * as React from 'react';
import { cn } from '@/lib/utils';

type ChartConfig = Record<
    string,
    {
        label: string;
        color: string;
    }
>;

type ChartContainerProps = React.ComponentProps<'div'> & {
    config: ChartConfig;
};

function ChartContainer({ config, className, children, ...props }: ChartContainerProps) {
    const cssVars = Object.entries(config).reduce(
        (acc, [key, value]) => {
            acc[`--color-${key}`] = value.color;

            return acc;
        },
        {} as Record<string, string>,
    );

    return (
        <div data-slot="chart-container" className={cn('flex aspect-video justify-center text-xs', className)} style={cssVars} {...props}>
            {children}
        </div>
    );
}

type ChartTooltipContentProps = {
    active?: boolean;
    payload?: Array<{
        name: string;
        value: number;
        color: string;
        dataKey: string;
    }>;
    label?: string;
    config?: ChartConfig;
};

function ChartTooltipContent({ active, payload, label, config }: ChartTooltipContentProps) {
    if (!active || !payload?.length) {
        return null;
    }

    return (
        <div className="bg-background rounded-lg border px-3 py-2 shadow-sm">
            {label && <p className="text-muted-foreground mb-1 text-xs">{label}</p>}
            <div className="flex flex-col gap-1">
                {payload.map((entry, index) => {
                    const configEntry = config?.[entry.dataKey];

                    return (
                        <div key={index} className="flex items-center gap-2 text-xs">
                            <div className="size-2.5 rounded-full" style={{ backgroundColor: entry.color || configEntry?.color }} />
                            <span className="text-muted-foreground">{configEntry?.label || entry.name}:</span>
                            <span className="font-medium">{entry.value}</span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

export { ChartContainer, ChartTooltipContent };
export type { ChartConfig };
