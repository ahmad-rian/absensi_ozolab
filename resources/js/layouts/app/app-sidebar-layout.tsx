import { usePage } from '@inertiajs/react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { ImpersonationBanner } from '@/components/impersonation-banner';
import { PenyelenggaraSidebar } from '@/components/penyelenggara-sidebar';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const { auth } = usePage().props as unknown as {
        auth: { user: { penyelenggara_id?: string | null } | null };
    };

    return (
        <AppShell variant="sidebar">
            {auth?.user?.penyelenggara_id ? (
                <PenyelenggaraSidebar />
            ) : (
                <AppSidebar />
            )}
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <ImpersonationBanner />
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
