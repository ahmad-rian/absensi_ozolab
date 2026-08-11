import { Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    BarChart3,
    Bell,
    BookOpen,
    Building2,
    CalendarCheck,
    Clock,
    CreditCard,
    FileSpreadsheet,
    FileText,
    Frame,
    GraduationCap,
    HardDrive,
    History,
    Images,
    LayoutGrid,
    LayoutTemplate,
    LifeBuoy,
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
import type { SchoolFeatureKey, SchoolFeatureMap } from '@/types';

/**
 * Menu dipetakan ke permission modul `<modul>.access`, sumber yang sama dengan
 * middleware di routes/web.php — sidebar tidak akan lagi menampilkan menu yang
 * berujung 403.
 *
 * `feature` adalah lapis kedua: fitur yang dimatikan sekolah menghilangkan
 * menunya, sejalan dengan middleware `feature:` di rute yang sama.
 */
type GuardedNavItem = BadgedNavItem & { permission: string; feature?: SchoolFeatureKey };

type NavSection = { label: string; items: GuardedNavItem[] };

const overviewItems: GuardedNavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid, permission: 'dashboard.access' },
];

const sections: NavSection[] = [
    {
        label: 'Master Data',
        items: [
            { title: 'Siswa', href: '/admin/siswa', icon: GraduationCap, permission: 'siswa.access', feature: 'master_siswa' },
            { title: 'Impor Siswa', href: '/admin/siswa/import', icon: FileSpreadsheet, permission: 'siswa.access', feature: 'master_siswa' },
            { title: 'Orang Tua', href: '/admin/orang-tua', icon: Users, permission: 'orang-tua.access', feature: 'master_siswa' },
            { title: 'Kelas', href: '/admin/kelas', icon: School, permission: 'kelas.access', feature: 'master_siswa' },
            { title: 'Kenaikan Kelas', href: '/admin/kelas/kenaikan', icon: ArrowUpRight, permission: 'kelas.access', feature: 'master_siswa' },
        ],
    },
    {
        label: 'Absensi',
        items: [
            { title: 'Absensi', href: '/admin/absensi', icon: CalendarCheck, permission: 'absensi.access', feature: 'absensi_sekolah' },
            { title: 'Jadwal Absensi', href: '/admin/jadwal-absensi', icon: Clock, permission: 'jadwal-absensi.access', feature: 'absensi_sekolah' },
            { title: 'Kartu RFID', href: '/admin/rfid-cards', icon: CreditCard, permission: 'rfid-cards.access', feature: 'absensi_rfid' },
        ],
    },
    {
        label: 'Kartu & Album',
        items: [
            { title: 'Frame & Bingkai', href: '/admin/frames', icon: Frame, permission: 'frames.access', feature: 'kartu_album' },
            { title: 'Layout Kartu', href: '/admin/card-layouts', icon: LayoutTemplate, permission: 'card-layouts.access', feature: 'kartu_album' },
            { title: 'Riwayat Kartu', href: '/admin/card-generation', icon: History, permission: 'card-generation.access', feature: 'kartu_album' },
            { title: 'Layout Album', href: '/admin/album-layouts', icon: BookOpen, permission: 'album-layouts.access', feature: 'kartu_album' },
            { title: 'Generate Album', href: '/admin/album-generation', icon: Printer, permission: 'album-generation.access', feature: 'kartu_album' },
            { title: 'Generate Pas Foto', href: '/admin/pas-foto', icon: Images, permission: 'photo-sheets.access', feature: 'kartu_album' },
        ],
    },
    {
        label: 'Laporan',
        items: [
            { title: 'Laporan', href: '/admin/laporan', icon: BarChart3, permission: 'laporan.access', feature: 'laporan' },
            { title: 'Notifikasi', href: '/admin/notifikasi', icon: Bell, permission: 'notifikasi.access', feature: 'inbox_notifikasi' },
        ],
    },
    {
        label: 'Pengguna & Akses',
        items: [
            { title: 'Pengguna', href: '/admin/users', icon: UserCog, permission: 'users.access', feature: 'manajemen_pengguna' },
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
        label: 'Bantuan',
        items: [
            // Tanpa permission dan tanpa feature: isinya yang menyesuaikan role
            // dan fitur sekolah, bukan menunya. Admin yang menu-nya banyak
            // dimatikan tetap butuh tahu cara memakai sisanya.
            { title: 'Panduan', href: '/admin/panduan', icon: LifeBuoy, permission: '' },
        ],
    },
    {
        label: 'Integrasi & Pengaturan',
        items: [
            { title: 'Gateway Notifikasi', href: '/admin/notification-gateways', icon: MessageSquare, permission: 'notification-gateways.access' },
            { title: 'WhatsApp', href: '/admin/wa-config', icon: MessageSquare, permission: 'wa-config.access', feature: 'integrasi_whatsapp' },
            { title: 'Google Drive', href: '/admin/drive-config', icon: HardDrive, permission: 'drive-config.access', feature: 'integrasi_drive' },
            { title: 'Pengaturan', href: '/admin/pengaturan', icon: Settings, permission: 'pengaturan.access' },
        ],
    },
];

export function AppSidebar() {
    const { auth, notifications, features } = usePage().props as unknown as {
        auth: { user: { roles?: string[]; permissions?: string[] } | null };
        notifications?: { unread: number };
        features?: Partial<SchoolFeatureMap>;
    };

    const isSuperAdmin = (auth?.user?.roles ?? []).includes('SUPER_ADMIN');
    const granted = auth?.user?.permissions ?? [];
    const unread = notifications?.unread ?? 0;

    const visible = useMemo(() => {
        // Prop `features` selalu utuh dari server. Kalau benar-benar absen,
        // anggap semua nyala — server tetap yang menolak, jadi sidebar tidak
        // perlu ikut fail-closed dan mengosongkan dirinya sendiri.
        const featureOn = (key?: SchoolFeatureKey) =>
            key === undefined || features === undefined || features[key] !== false;

        return (items: GuardedNavItem[]) =>
            items
                // `isSuperAdmin` hanya melompati cek permission, BUKAN cek
                // fitur — sama persis dengan aturan di middleware `feature:`.
                .filter((item) => (item.permission === '' || isSuperAdmin || granted.includes(item.permission)) && featureOn(item.feature))
                .map((item) =>
                    item.href === '/admin/notifikasi' ? { ...item, badge: unread } : item,
                );
    }, [isSuperAdmin, granted, unread, features]);

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
