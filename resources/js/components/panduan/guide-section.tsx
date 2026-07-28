import { AlertTriangle, Info, Lightbulb } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * Bingkai isi satu topik panduan.
 *
 * Sengaja komponen kecil dan bukan markdown: isinya ikut versi kode, jadi
 * langkah yang disebut di sini tidak bisa basi diam-diam saat menunya berubah.
 */
export function Steps({ children }: { children: ReactNode }) {
    return <ol className="ml-5 list-decimal space-y-2 text-sm leading-relaxed">{children}</ol>;
}

export function Bullets({ children }: { children: ReactNode }) {
    return <ul className="ml-5 list-disc space-y-1.5 text-sm leading-relaxed">{children}</ul>;
}

export function Para({ children }: { children: ReactNode }) {
    return <p className="text-sm leading-relaxed">{children}</p>;
}

/** Catatan biasa — informasi tambahan yang berguna tapi tidak genting. */
export function Note({ children }: { children: ReactNode }) {
    return (
        <div className="bg-muted/50 flex gap-2.5 rounded-lg border p-3">
            <Info className="text-muted-foreground mt-0.5 size-4 shrink-0" />
            <div className="text-muted-foreground text-sm leading-relaxed">{children}</div>
        </div>
    );
}

/** Jebakan — hal yang kalau terlewat menimbulkan kerugian nyata. */
export function Pitfall({ children }: { children: ReactNode }) {
    return (
        <div className="border-destructive/30 bg-destructive/10 text-destructive flex gap-2.5 rounded-lg border p-3">
            <AlertTriangle className="mt-0.5 size-4 shrink-0" />
            <div className="text-sm leading-relaxed">{children}</div>
        </div>
    );
}

/** Kiat — cara yang lebih cepat atau lebih aman. */
export function Tip({ children }: { children: ReactNode }) {
    return (
        <div className="flex gap-2.5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3 text-emerald-700 dark:text-emerald-300">
            <Lightbulb className="mt-0.5 size-4 shrink-0" />
            <div className="text-sm leading-relaxed">{children}</div>
        </div>
    );
}

export function Menu({ children }: { children: ReactNode }) {
    return <span className="bg-muted rounded px-1.5 py-0.5 font-medium">{children}</span>;
}
