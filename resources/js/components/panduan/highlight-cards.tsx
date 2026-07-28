import type { LucideIcon } from 'lucide-react';

export type Highlight = {
    icon: LucideIcon;
    title: string;
    description: string;
};

/** Tiga sorotan singkat di awal topik — untuk menangkap gambaran sebelum membaca detailnya. */
export function HighlightCards({ items }: { items: Highlight[] }) {
    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {items.map(({ icon: Icon, title, description }) => (
                <div key={title} className="bg-muted/40 rounded-xl border p-4">
                    <Icon className="text-primary mb-2.5 size-5" />
                    <p className="text-sm font-semibold">{title}</p>
                    <p className="text-muted-foreground mt-1 text-xs leading-relaxed">{description}</p>
                </div>
            ))}
        </div>
    );
}
