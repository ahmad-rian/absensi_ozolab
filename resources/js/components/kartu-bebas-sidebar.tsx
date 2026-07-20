import { Link } from '@inertiajs/react';
import { Database, FileText, Frame, History, LayoutGrid, LayoutTemplate, Wand2 } from 'lucide-react';
import { NavGroup } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

const mainItems: NavItem[] = [
    { title: 'Dashboard', href: '/kartu-bebas', icon: LayoutGrid },
    { title: 'Data', href: '/kartu-bebas/data', icon: Database },
    { title: 'Generate', href: '/kartu-bebas/generate', icon: Wand2 },
];

const cardItems: NavItem[] = [
    { title: 'Frame & Bingkai', href: '/kartu-bebas/frames', icon: Frame },
    { title: 'Layout Kartu', href: '/kartu-bebas/layouts', icon: LayoutTemplate },
    { title: 'Riwayat Kartu', href: '/kartu-bebas/riwayat', icon: History },
];

export function KartuBebasSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/kartu-bebas" prefetch>
                                <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-emerald-600 text-white">
                                    <FileText className="size-5" />
                                </div>
                                <div className="grid flex-1 text-left text-sm leading-tight">
                                    <span className="truncate font-semibold">Kartu Bebas / Haji</span>
                                    <span className="text-muted-foreground truncate text-xs">Workspace kartu</span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavGroup label="Utama" items={mainItems} />
                <NavGroup label="Kartu" items={cardItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
