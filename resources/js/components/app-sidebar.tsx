import { Link } from '@inertiajs/react';
import {
    BarChart3,
    Bell,
    Building2,
    CalendarCheck,
    GraduationCap,
    LayoutGrid,
    QrCode,
    School,
    Settings,
    ShieldCheck,
    UserCog,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavGroup } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { SchoolSwitcher } from '@/components/school-switcher';
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

const overviewItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
];

const academicItems: NavItem[] = [
    { title: 'Siswa', href: '/admin/siswa', icon: GraduationCap },
    { title: 'Orang Tua', href: '/admin/orang-tua', icon: Users },
    { title: 'Kelas', href: '/admin/kelas', icon: School },
    { title: 'Absensi', href: '/admin/absensi', icon: CalendarCheck },
    { title: 'Scanner', href: '/admin/scanner', icon: QrCode },
];

const reportItems: NavItem[] = [
    { title: 'Laporan', href: '/admin/laporan', icon: BarChart3 },
    { title: 'Notifikasi', href: '/admin/notifikasi', icon: Bell },
];

const adminItems: NavItem[] = [
    { title: 'Pengguna', href: '/admin/users', icon: UserCog },
    { title: 'Sekolah', href: '/admin/schools', icon: Building2 },
    { title: 'Role & Izin', href: '/admin/roles', icon: ShieldCheck },
    { title: 'Pengaturan', href: '/admin/pengaturan', icon: Settings },
];

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
                    <SidebarMenuItem>
                        <SchoolSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavGroup label="Utama" items={overviewItems} />
                <NavGroup label="Akademik" items={academicItems} />
                <NavGroup label="Laporan" items={reportItems} />
                <NavGroup label="Administrasi" items={adminItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
