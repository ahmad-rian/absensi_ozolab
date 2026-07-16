import { type CSSProperties } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { KartuBebasSidebar } from '@/components/kartu-bebas-sidebar';
import type { AppLayoutProps } from '@/types';

// Green-toned workspace: override the primary/accent CSS vars to emerald so all
// primary buttons, active nav, and rings render green within this workspace only.
const greenTheme = {
    '--primary': 'oklch(0.6907 0.1554 160.3454)',
    '--primary-foreground': 'oklch(0.9789 0.0082 121.6272)',
    '--sidebar-primary': 'oklch(0.6907 0.1554 160.3454)',
    '--sidebar-primary-foreground': 'oklch(0.9789 0.0082 121.6272)',
    '--ring': 'oklch(0.6907 0.1554 160.3454)',
    '--sidebar-ring': 'oklch(0.6907 0.1554 160.3454)',
} as CSSProperties;

export default function KartuBebasLayout({ children, breadcrumbs = [] }: AppLayoutProps) {
    return (
        <div style={greenTheme}>
            <AppShell variant="sidebar">
                <KartuBebasSidebar />
                <AppContent variant="sidebar" className="overflow-x-hidden">
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                    {children}
                </AppContent>
            </AppShell>
        </div>
    );
}
