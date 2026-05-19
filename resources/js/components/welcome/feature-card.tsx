import type { ReactNode } from 'react';

interface FeatureCardProps {
    icon: ReactNode;
    title: string;
    description: string;
}

export function FeatureCard({ icon, title, description }: FeatureCardProps) {
    return (
        <div
            className="bg-card group rounded-2xl border border-border p-6 transition-all duration-200 hover:border-[var(--primary)] hover:scale-[1.02]"
        >
            <div className="bg-accent mb-4 flex size-12 items-center justify-center rounded-xl">
                {icon}
            </div>
            <h3 className="text-foreground text-base font-bold">{title}</h3>
            <p className="text-muted-foreground mt-2 text-sm leading-relaxed">{description}</p>
        </div>
    );
}
