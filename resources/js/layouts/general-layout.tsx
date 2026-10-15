import type { CSSProperties } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { PemilihKonteks } from '@/components/general/pemilih-konteks';
import { GeneralSidebar } from '@/components/general-sidebar';
import { ImpersonationBanner } from '@/components/impersonation-banner';
import type { AppLayoutProps } from '@/types';

/**
 * Workspace Absensi General (spec 009). Warna aksen indigo khusus area ini
 * supaya super admin selalu tahu ia tidak sedang di area sekolah.
 */
const temaGeneral = {
    '--primary': 'oklch(0.5106 0.2301 276.97)',
    '--primary-foreground': 'oklch(0.9851 0 0)',
    '--sidebar-primary': 'oklch(0.5106 0.2301 276.97)',
    '--sidebar-primary-foreground': 'oklch(0.9851 0 0)',
    '--ring': 'oklch(0.5106 0.2301 276.97)',
    '--sidebar-ring': 'oklch(0.5106 0.2301 276.97)',
    '--sidebar-accent': 'oklch(0.95 0.03 277)',
    '--sidebar-accent-foreground': 'oklch(0.45 0.2 277)',
} as CSSProperties;

export default function GeneralLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <div style={temaGeneral}>
            <AppShell variant="sidebar">
                <GeneralSidebar />
                <AppContent variant="sidebar" className="overflow-x-hidden">
                    <ImpersonationBanner />
                    <AppSidebarHeader
                        breadcrumbs={breadcrumbs}
                        kanan={<PemilihKonteks />}
                    />
                    {children}
                </AppContent>
            </AppShell>
        </div>
    );
}
