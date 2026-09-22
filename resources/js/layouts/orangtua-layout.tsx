import type { CSSProperties } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { OrangtuaSidebar } from '@/components/orangtua-sidebar';
import type { AppLayoutProps } from '@/types';

/*
    Biru laut yang DIPILIH, bukan diwarisi.

    Nilai sebelumnya `oklch(0.6907 …)` terlalu terang untuk teks putih di
    atasnya: tombol primer terbaca pucat dan tidak seperti tombol. Lightness
    diturunkan ke 0.52 sehingga rasio kontras terhadap putih lewat ambang
    WCAG AA, dan tetap jelas berbeda dari biru admin maupun hijau kartu bebas.
*/
const portalTheme = {
    '--primary': 'oklch(0.52 0.15 242)',
    '--primary-foreground': 'oklch(0.99 0.005 240)',
    '--sidebar-primary': 'oklch(0.52 0.15 242)',
    '--sidebar-primary-foreground': 'oklch(0.99 0.005 240)',
    '--ring': 'oklch(0.52 0.15 242)',
    '--sidebar-ring': 'oklch(0.52 0.15 242)',
} as CSSProperties;

export default function OrangtuaLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <div style={portalTheme}>
            <AppShell variant="sidebar">
                <OrangtuaSidebar />
                <AppContent variant="sidebar" className="overflow-x-hidden">
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                    {children}
                </AppContent>
            </AppShell>
        </div>
    );
}
