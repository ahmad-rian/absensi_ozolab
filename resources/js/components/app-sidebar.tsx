import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Bell,
    BookOpen,
    Building2,
    CalendarCheck,
    Clock,
    CreditCard,
    Frame,
    GraduationCap,
    HardDrive,
    MessageSquare,
    LayoutGrid,
    LayoutTemplate,
    Printer,
    QrCode,
    School,
    Settings,
    ShieldCheck,
    UserCog,
    Users,
} from 'lucide-react';
import { useMemo } from 'react';
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
    { title: 'Jadwal Absensi', href: '/admin/jadwal-absensi', icon: Clock },
    { title: 'Scanner', href: '/admin/scanner', icon: QrCode },
];

const cardItems: NavItem[] = [
    { title: 'Frame & Bingkai', href: '/admin/frames', icon: Frame },
    { title: 'Layout Kartu', href: '/admin/card-layouts', icon: LayoutTemplate },
    { title: 'Generate Kartu', href: '/admin/card-generation', icon: CreditCard },
    { title: 'Layout Album', href: '/admin/album-layouts', icon: BookOpen },
    { title: 'Generate Album', href: '/admin/album-generation', icon: Printer },
];

const reportItems: NavItem[] = [
    { title: 'Laporan', href: '/admin/laporan', icon: BarChart3 },
    { title: 'Notifikasi', href: '/admin/notifikasi', icon: Bell },
];

// Admin items — filtered by role
const adminItemsSuperAdmin: NavItem[] = [
    { title: 'Pengguna', href: '/admin/users', icon: UserCog },
    { title: 'Sekolah', href: '/admin/schools', icon: Building2 },
    { title: 'Role & Izin', href: '/admin/roles', icon: ShieldCheck },
    { title: 'WhatsApp', href: '/admin/wa-config', icon: MessageSquare },
    { title: 'Google Drive', href: '/admin/drive-config', icon: HardDrive },
    { title: 'Pengaturan', href: '/admin/pengaturan', icon: Settings },
];

const adminItemsAdmin: NavItem[] = [
    { title: 'Pengguna', href: '/admin/users', icon: UserCog },
    { title: 'WhatsApp', href: '/admin/wa-config', icon: MessageSquare },
    { title: 'Google Drive', href: '/admin/drive-config', icon: HardDrive },
    { title: 'Pengaturan', href: '/admin/pengaturan', icon: Settings },
];

export function AppSidebar() {
    const { auth } = usePage().props as unknown as { auth: { user: { roles: string[] } | null } };
    const roles = auth?.user?.roles ?? [];

    const isSuperAdmin = roles.includes('SUPER_ADMIN');
    const isAdmin = roles.includes('ADMIN');

    const adminItems = useMemo(() => {
        if (isSuperAdmin) return adminItemsSuperAdmin;
        if (isAdmin) return adminItemsAdmin;
        return [];
    }, [isSuperAdmin, isAdmin]);

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
                <NavGroup label="Kartu & Album" items={cardItems} />
                <NavGroup label="Laporan" items={reportItems} />
                {adminItems.length > 0 && (
                    <NavGroup label="Administrasi" items={adminItems} />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
