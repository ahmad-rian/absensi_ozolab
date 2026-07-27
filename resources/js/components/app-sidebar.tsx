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
    LayoutGrid,
    LayoutTemplate,
    MessageSquare,
    Printer,
    School,
    Settings,
    ShieldCheck,
    UserCog,
    Users,
} from 'lucide-react';
import { useMemo } from 'react';
import AppLogo from '@/components/app-logo';
import { NavCollapsibleGroup, NavGroup, type BadgedNavItem } from '@/components/nav-main';
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

/**
 * Menu dipetakan ke permission modul `<modul>.access`, sumber yang sama dengan
 * middleware di routes/web.php — sidebar tidak akan lagi menampilkan menu yang
 * berujung 403.
 */
type GuardedNavItem = BadgedNavItem & { permission: string };

type NavSection = { label: string; items: GuardedNavItem[] };

const overviewItems: GuardedNavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid, permission: 'dashboard.access' },
];

const sections: NavSection[] = [
    {
        label: 'Master Data',
        items: [
            { title: 'Siswa', href: '/admin/siswa', icon: GraduationCap, permission: 'siswa.access' },
            { title: 'Orang Tua', href: '/admin/orang-tua', icon: Users, permission: 'orang-tua.access' },
            { title: 'Kelas', href: '/admin/kelas', icon: School, permission: 'kelas.access' },
        ],
    },
    {
        label: 'Absensi',
        items: [
            { title: 'Absensi', href: '/admin/absensi', icon: CalendarCheck, permission: 'absensi.access' },
            { title: 'Jadwal Absensi', href: '/admin/jadwal-absensi', icon: Clock, permission: 'jadwal-absensi.access' },
        ],
    },
    {
        label: 'Kartu & Album',
        items: [
            { title: 'Frame & Bingkai', href: '/admin/frames', icon: Frame, permission: 'frames.access' },
            { title: 'Layout Kartu', href: '/admin/card-layouts', icon: LayoutTemplate, permission: 'card-layouts.access' },
            { title: 'Riwayat Kartu', href: '/admin/card-generation', icon: History, permission: 'card-generation.access' },
            { title: 'Layout Album', href: '/admin/album-layouts', icon: BookOpen, permission: 'album-layouts.access' },
            { title: 'Generate Album', href: '/admin/album-generation', icon: Printer, permission: 'album-generation.access' },
        ],
    },
    {
        label: 'Laporan',
        items: [
            { title: 'Laporan', href: '/admin/laporan', icon: BarChart3, permission: 'laporan.access' },
            { title: 'Notifikasi', href: '/admin/notifikasi', icon: Bell, permission: 'notifikasi.access' },
        ],
    },
    {
        label: 'Pengguna & Akses',
        items: [
            { title: 'Pengguna', href: '/admin/users', icon: UserCog, permission: 'users.access' },
            { title: 'Role & Hak Akses', href: '/admin/roles', icon: ShieldCheck, permission: 'roles.access' },
        ],
    },
    {
        label: 'Sekolah',
        items: [
            { title: 'Sekolah', href: '/admin/schools', icon: Building2, permission: 'schools.access' },
            { title: 'Kartu Bebas / Haji', href: '/kartu-bebas', icon: FileText, newTab: true, permission: 'kartu-bebas.access' },
        ],
    },
    {
        label: 'Integrasi & Pengaturan',
        items: [
            { title: 'Gateway Notifikasi', href: '/admin/notification-gateways', icon: MessageSquare, permission: 'notification-gateways.access' },
            { title: 'WhatsApp', href: '/admin/wa-config', icon: MessageSquare, permission: 'wa-config.access' },
            { title: 'Google Drive', href: '/admin/drive-config', icon: HardDrive, permission: 'drive-config.access' },
            { title: 'Pengaturan', href: '/admin/pengaturan', icon: Settings, permission: 'pengaturan.access' },
        ],
    },
];

export function AppSidebar() {
    const { auth, notifications } = usePage().props as unknown as {
        auth: { user: { roles?: string[]; permissions?: string[] } | null };
        notifications?: { unread: number };
    };

    const isSuperAdmin = (auth?.user?.roles ?? []).includes('SUPER_ADMIN');
    const granted = auth?.user?.permissions ?? [];
    const unread = notifications?.unread ?? 0;

    const visible = useMemo(
        () => (items: GuardedNavItem[]) =>
            items
                .filter((item) => isSuperAdmin || granted.includes(item.permission))
                .map((item) =>
                    item.href === '/admin/notifikasi' ? { ...item, badge: unread } : item,
                ),
        [isSuperAdmin, granted, unread],
    );

    const overview = visible(overviewItems);

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
                <NavGroup label="Utama" items={overview} />
                {sections.map((section) => (
                    <NavCollapsibleGroup key={section.label} label={section.label} items={visible(section.items)} />
                ))}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
