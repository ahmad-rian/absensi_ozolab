import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    BookOpen,
    Building2,
    CalendarRange,
    ClipboardCheck,
    Database,
    FileSpreadsheet,
    Frame,
    HardDrive,
    History,
    LayoutGrid,
    LayoutTemplate,
    LifeBuoy,
    MessageSquare,
    Settings,
    Shapes,
    UserCog,
    Users,
    Wand2,
} from 'lucide-react';
import BrandLogo from '@/components/brand-logo';
import { NavCollapsibleGroup, NavGroup } from '@/components/nav-main';
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
import type { NavItem } from '@/types';

export type GeneralSection = { label: string; items: NavItem[] };

/**
 * Menu workspace Absensi General (spec 009), dikelompokkan seperti sidebar
 * sekolah. "Haji (kartu lama)" memakai rute `/kartu-bebas/*` yang sudah ada:
 * hanya letak menunya yang pindah, data dan link scan kartu tercetak tetap.
 */
export const generalUtama: NavItem[] = [
    { title: 'Ringkasan', href: '/general', icon: LayoutGrid },
    { title: 'Penyelenggara', href: '/general/penyelenggara', icon: Building2 },
    { title: 'Pengguna', href: '/general/pengguna', icon: UserCog },
];

export const generalSections: GeneralSection[] = [
    {
        label: 'Kategori & Program',
        items: [
            { title: 'Kategori', href: '/general/kategori', icon: Shapes },
            { title: 'Program', href: '/general/program', icon: CalendarRange },
        ],
    },
    {
        label: 'Haji (kartu lama)',
        items: [
            { title: 'Dashboard Haji', href: '/kartu-bebas', icon: BookOpen },
            { title: 'Peserta', href: '/kartu-bebas/peserta', icon: Users },
            { title: 'Format Data', href: '/kartu-bebas/data', icon: Database },
            {
                title: 'Absensi & Link Scan',
                href: '/kartu-bebas/absensi',
                icon: ClipboardCheck,
            },
            {
                title: 'Laporan Absensi',
                href: '/kartu-bebas/laporan',
                icon: FileSpreadsheet,
            },
            { title: 'Generate', href: '/kartu-bebas/generate', icon: Wand2 },
        ],
    },
    {
        label: 'Desain Kartu',
        items: [
            {
                title: 'Frame & Bingkai',
                href: '/kartu-bebas/frames',
                icon: Frame,
            },
            {
                title: 'Layout Kartu',
                href: '/kartu-bebas/layouts',
                icon: LayoutTemplate,
            },
            {
                title: 'Riwayat Kartu',
                href: '/kartu-bebas/riwayat',
                icon: History,
            },
        ],
    },
    {
        label: 'Pengaturan',
        items: [
            {
                title: 'Umum & Branding',
                href: '/general/pengaturan',
                icon: Settings,
            },
            {
                title: 'Google Drive',
                href: '/general/pengaturan#drive',
                icon: HardDrive,
            },
            {
                title: 'WhatsApp',
                href: '/general/pengaturan#whatsapp',
                icon: MessageSquare,
            },
        ],
    },
    {
        label: 'Bantuan',
        items: [{ title: 'Panduan', href: '/admin/panduan', icon: LifeBuoy }],
    },
];

export function GeneralSidebar() {
    const { app } = usePage<{
        app: { workspace_name?: string | null };
    }>().props;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/general" prefetch>
                                <BrandLogo className="size-8 rounded-lg" />
                                <div className="grid flex-1 text-left text-sm leading-tight">
                                    <span className="truncate font-semibold">
                                        {app?.workspace_name ??
                                            'Absensi General'}
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        Workspace super admin
                                    </span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavGroup label="Utama" items={generalUtama} />
                {generalSections.map((section) => (
                    <NavCollapsibleGroup
                        key={section.label}
                        label={section.label}
                        items={section.items}
                    />
                ))}
            </SidebarContent>

            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            tooltip={{ children: 'Kembali ke Sekolah' }}
                        >
                            <a href="/dashboard">
                                <ArrowLeft />
                                <span>Kembali ke Sekolah</span>
                            </a>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
