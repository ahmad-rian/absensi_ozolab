import { Link } from '@inertiajs/react';
import {
    BarChart3,
    Bell,
    CalendarCheck,
    GraduationCap,
    LayoutGrid,
    QrCode,
    School,
    Settings,
    ShieldCheck,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'Siswa', href: '/admin/siswa', icon: GraduationCap },
    { title: 'Orang Tua', href: '/admin/orang-tua', icon: Users },
    { title: 'Kelas', href: '/admin/kelas', icon: School },
    { title: 'Absensi', href: '/admin/absensi', icon: CalendarCheck },
    { title: 'Scanner', href: '/admin/scanner', icon: QrCode },
    { title: 'Laporan', href: '/admin/laporan', icon: BarChart3 },
    { title: 'Notifikasi', href: '/admin/notifikasi', icon: Bell },
    { title: 'Role & Izin', href: '/admin/roles', icon: ShieldCheck },
    { title: 'Pengaturan', href: '/admin/pengaturan', icon: Settings },
];

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                {footerNavItems.length > 0 && <NavFooter items={footerNavItems} className="mt-auto" />}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
