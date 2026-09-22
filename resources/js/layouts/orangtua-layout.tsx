import type { CSSProperties } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { OrangtuaSidebar } from '@/components/orangtua-sidebar';
import type { AppLayoutProps } from '@/types';

const portalTheme = {
    '--primary': 'oklch(0.6907 0.1554 230)',
    '--primary-foreground': 'oklch(0.9789 0.0082 121.6272)',
    '--sidebar-primary': 'oklch(0.6907 0.1554 230)',
    '--sidebar-primary-foreground': 'oklch(0.9789 0.0082 121.6272)',
    '--ring': 'oklch(0.6907 0.1554 230)',
    '--sidebar-ring': 'oklch(0.6907 0.1554 230)',
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
