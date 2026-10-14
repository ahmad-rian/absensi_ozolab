import type {CSSProperties} from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { PenyelenggaraSidebar } from '@/components/penyelenggara-sidebar';
import type { AppLayoutProps } from '@/types';

// Area penyelenggara memakai warna biru-laut sendiri supaya admin yang juga
// membuka panel sekolah selalu tahu sedang berada di tenant mana.
const tealTheme = {
    '--primary': 'oklch(0.5413 0.0952 212.8)',
    '--primary-foreground': 'oklch(0.9851 0 0)',
    '--sidebar-primary': 'oklch(0.5413 0.0952 212.8)',
    '--sidebar-primary-foreground': 'oklch(0.9851 0 0)',
    '--ring': 'oklch(0.5413 0.0952 212.8)',
    '--sidebar-ring': 'oklch(0.5413 0.0952 212.8)',
} as CSSProperties;

export default function PenyelenggaraLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <div style={tealTheme}>
            <AppShell variant="sidebar">
                <PenyelenggaraSidebar />
                <AppContent variant="sidebar" className="overflow-x-hidden">
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                    {children}
                </AppContent>
            </AppShell>
        </div>
    );
}
