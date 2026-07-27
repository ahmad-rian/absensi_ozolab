import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Bell,
    BookOpen,
    Building2,
    CalendarCheck,
    Clock,
    FileText,
    Frame,
    GraduationCap,
    HardDrive,
    History,
    MessageSquare,
    MoonStar,
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

/**
 * Menu dipetakan ke permission modul `<modul>.access`, sumber yang sama dengan
 * middleware di routes/web.php — sidebar tidak akan lagi menampilkan menu yang
 * berujung 403.
 */
type GuardedNavItem = NavItem & { permission: string };

const overviewItems: GuardedNavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid, permission: 'dashboard.access' },
];

const academicItems: GuardedNavItem[] = [
    { title: 'Siswa', href: '/admin/siswa', icon: GraduationCap, permission: 'siswa.access' },
    { title: 'Orang Tua', href: '/admin/orang-tua', icon: Users, permission: 'orang-tua.access' },
    { title: 'Kelas', href: '/admin/kelas', icon: School, permission: 'kelas.access' },
    { title: 'Absensi', href: '/admin/absensi', icon: CalendarCheck, permission: 'absensi.access' },
    { title: 'Jadwal Absensi', href: '/admin/jadwal-absensi', icon: Clock, permission: 'jadwal-absensi.access' },
    { title: 'Scanner', href: '/admin/scanner', icon: QrCode, permission: 'scanner.access' },
    { title: 'Scanner Sholat', href: '/admin/scanner/sholat', icon: MoonStar, permission: 'scanner.access' },
];

const cardItems: GuardedNavItem[] = [
    { title: 'Frame & Bingkai', href: '/admin/frames', icon: Frame, permission: 'frames.access' },
    { title: 'Layout Kartu', href: '/admin/card-layouts', icon: LayoutTemplate, permission: 'card-layouts.access' },
    { title: 'Riwayat Kartu', href: '/admin/card-generation', icon: History, permission: 'card-generation.access' },
    { title: 'Layout Album', href: '/admin/album-layouts', icon: BookOpen, permission: 'album-layouts.access' },
    { title: 'Generate Album', href: '/admin/album-generation', icon: Printer, permission: 'album-generation.access' },
];

const reportItems: GuardedNavItem[] = [
    { title: 'Laporan', href: '/admin/laporan', icon: BarChart3, permission: 'laporan.access' },
    { title: 'Notifikasi', href: '/admin/notifikasi', icon: Bell, permission: 'notifikasi.access' },
];

const adminItems: GuardedNavItem[] = [
    { title: 'Kartu Bebas / Haji', href: '/kartu-bebas', icon: FileText, newTab: true, permission: 'kartu-bebas.access' },
    { title: 'Pengguna', href: '/admin/users', icon: UserCog, permission: 'users.access' },
    { title: 'Sekolah', href: '/admin/schools', icon: Building2, permission: 'schools.access' },
    { title: 'Role & Hak Akses', href: '/admin/roles', icon: ShieldCheck, permission: 'roles.access' },
    { title: 'Gateway Notifikasi', href: '/admin/notification-gateways', icon: MessageSquare, permission: 'notification-gateways.access' },
    { title: 'WhatsApp', href: '/admin/wa-config', icon: MessageSquare, permission: 'wa-config.access' },
    { title: 'Google Drive', href: '/admin/drive-config', icon: HardDrive, permission: 'drive-config.access' },
    { title: 'Pengaturan', href: '/admin/pengaturan', icon: Settings, permission: 'pengaturan.access' },
];

export function AppSidebar() {
    const { auth } = usePage().props as unknown as {
        auth: { user: { roles?: string[]; permissions?: string[] } | null };
    };

    const isSuperAdmin = (auth?.user?.roles ?? []).includes('SUPER_ADMIN');
    const granted = auth?.user?.permissions ?? [];

    const visible = useMemo(
        () => (items: GuardedNavItem[]) =>
            isSuperAdmin ? items : items.filter((item) => granted.includes(item.permission)),
        [isSuperAdmin, granted],
    );

    const overview = visible(overviewItems);
    const academic = visible(academicItems);
    const cards = visible(cardItems);
    const reports = visible(reportItems);
    const administration = visible(adminItems);

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
                {overview.length > 0 && <NavGroup label="Utama" items={overview} />}
                {academic.length > 0 && <NavGroup label="Akademik" items={academic} />}
                {cards.length > 0 && <NavGroup label="Kartu & Album" items={cards} />}
                {reports.length > 0 && <NavGroup label="Laporan" items={reports} />}
                {administration.length > 0 && <NavGroup label="Administrasi" items={administration} />}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
