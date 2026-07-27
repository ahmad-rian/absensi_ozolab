import { createContext, useContext, type ReactNode } from 'react';
import { cn } from '@/lib/utils';

type TabsContextValue = {
    value: string;
    onValueChange: (value: string) => void;
};

const TabsContext = createContext<TabsContextValue | null>(null);

function useTabs(component: string): TabsContextValue {
    const context = useContext(TabsContext);

    if (!context) {
        throw new Error(`<${component}> must be rendered inside <Tabs>.`);
    }

    return context;
}

type TabsProps = {
    value: string;
    onValueChange: (value: string) => void;
    className?: string;
    children: ReactNode;
};

/**
 * Tabs terkontrol tanpa dependency Radix — cukup untuk kebutuhan aplikasi
 * ini dan menjaga bundle tetap ramping.
 */
export function Tabs({ value, onValueChange, className, children }: TabsProps) {
    return (
        <TabsContext.Provider value={{ value, onValueChange }}>
            <div data-slot="tabs" className={cn('flex flex-col gap-6', className)}>
                {children}
            </div>
        </TabsContext.Provider>
    );
}

export function TabsList({ className, children }: { className?: string; children: ReactNode }) {
    return (
        <div
            role="tablist"
            data-slot="tabs-list"
            className={cn(
                'bg-muted text-muted-foreground inline-flex w-fit max-w-full items-center gap-1 overflow-x-auto rounded-lg p-1',
                className,
            )}
        >
            {children}
        </div>
    );
}

export function TabsTrigger({
    value,
    className,
    children,
}: {
    value: string;
    className?: string;
    children: ReactNode;
}) {
    const tabs = useTabs('TabsTrigger');
    const active = tabs.value === value;

    return (
        <button
            type="button"
            role="tab"
            aria-selected={active}
            data-state={active ? 'active' : 'inactive'}
            onClick={() => tabs.onValueChange(value)}
            className={cn(
                'inline-flex shrink-0 items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium whitespace-nowrap transition-colors',
                active ? 'bg-background text-foreground shadow-sm' : 'hover:text-foreground',
                className,
            )}
        >
            {children}
        </button>
    );
}

export function TabsContent({
    value,
    className,
    children,
}: {
    value: string;
    className?: string;
    children: ReactNode;
}) {
    const tabs = useTabs('TabsContent');

    if (tabs.value !== value) {
        return null;
    }

    return (
        <div role="tabpanel" data-slot="tabs-content" className={className}>
            {children}
        </div>
    );
}
