import type { ReactNode } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Nilai `--card` di Tailwind 4 sudah lengkap, jadi TIDAK boleh dibungkus
 * `hsl()`. Komponen dashboard lama masih membungkusnya dan menghasilkan warna
 * yang tidak valid — impor konstanta ini alih-alih menyalin ulang objeknya.
 */
export const TOOLTIP_STYLE = {
    backgroundColor: 'var(--card)',
    border: '1px solid var(--border)',
    borderRadius: '0.5rem',
    fontSize: '0.875rem',
} as const;

/**
 * Bingkai tri-state untuk seluruh chart: Skeleton saat data belum tiba,
 * pesan saat kosong, isi saat ada. Polanya sebelumnya tersalin di tiap chart
 * dan sudah mulai berbeda satu sama lain.
 */
export function ChartFrame({
    title,
    description,
    height = 300,
    isLoading,
    isEmpty,
    emptyMessage = 'Belum ada data pada rentang ini.',
    children,
}: {
    title: string;
    description?: string;
    height?: number;
    isLoading: boolean;
    isEmpty: boolean;
    emptyMessage?: string;
    children: ReactNode;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                {description && <CardDescription>{description}</CardDescription>}
            </CardHeader>
            <CardContent>
                {isLoading ? (
                    <Skeleton className="w-full" style={{ height }} />
                ) : isEmpty ? (
                    <p
                        className="text-muted-foreground flex items-center justify-center text-sm"
                        style={{ height }}
                    >
                        {emptyMessage}
                    </p>
                ) : (
                    children
                )}
            </CardContent>
        </Card>
    );
}
