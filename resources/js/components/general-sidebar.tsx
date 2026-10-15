import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    BookOpen,
    CalendarClock,
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
    Settings2,
    Shapes,
    UserCog,
    Users,
    Wand2,
} from 'lucide-react';
import BrandLogo from '@/components/brand-logo';
import { useKonteksGeneral } from '@/components/general/pemilih-konteks';
import type { KonteksAktif } from '@/components/general/pemilih-konteks';
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
    { title: 'Semua Kategori', href: '/general/kategori', icon: Shapes },
    { title: 'Semua Program', href: '/general/program', icon: CalendarRange },
    { title: 'Pengguna', href: '/general/pengguna', icon: UserCog },
];

/**
 * Menu kategori aktif. Sama untuk semua kategori — isinya mengikuti pilihan
 * di kanan atas. Haji lama memakai halaman `/kartu-bebas/*` yang sudah ada,
 * kategori baru memakai halaman program di area penyelenggara.
 */
export function menuKonteks(aktif: KonteksAktif | null): GeneralSection[] {
    if (!aktif || aktif.jenis === 'haji') {
        return [
            {
                label: aktif?.label ?? 'Haji (kartu lama)',
                items: [
                    {
                        title: 'Dashboard',
                        href: '/kartu-bebas',
                        icon: BookOpen,
                    },
                    {
                        title: 'Peserta',
                        href: '/kartu-bebas/peserta',
                        icon: Users,
                    },
                    {
                        title: 'Format Data',
                        href: '/kartu-bebas/data',
                        icon: Database,
                    },
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
                    {
                        title: 'Generate',
                        href: '/kartu-bebas/generate',
                        icon: Wand2,
                    },
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
        ];
    }

    const p = `/penyelenggara/program/${aktif.program_id}`;

    return [
        {
            label: aktif.kategori
                ? `${aktif.kategori} · ${aktif.label}`
                : aktif.label,
            items: [
                { title: 'Dashboard', href: `${p}/dashboard`, icon: BookOpen },
                { title: 'Peserta', href: `${p}/peserta`, icon: Users },
                {
                    title: 'Format Data',
                    href: `/penyelenggara/kategori/${aktif.kategori_id}`,
                    icon: Database,
                },
                {
                    title: 'Absensi & Link Scan',
                    href: `${p}/kehadiran`,
                    icon: ClipboardCheck,
                },
                { title: 'Jadwal', href: `${p}/jadwal`, icon: CalendarClock },
                {
                    title: 'Laporan Absensi',
                    href: `${p}/laporan`,
                    icon: FileSpreadsheet,
                },
                { title: 'Grup & Pengaturan', href: p, icon: Settings2 },
            ],
        },
        {
            label: 'Desain Kartu',
            items: [
                {
                    title: 'Layout Kartu',
                    href: `${p}/layout`,
                    icon: LayoutTemplate,
                },
                {
                    title: 'Frame & Bingkai',
                    href: '/kartu-bebas/frames',
                    icon: Frame,
                },
            ],
        },
    ];
}

export const generalSections: GeneralSection[] = [
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
    const konteks = useKonteksGeneral();
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
                {menuKonteks(konteks?.aktif ?? null).map((section, i) =>
                    i === 0 ? (
                        <NavGroup
                            key={section.label}
                            label={section.label}
                            items={section.items}
                        />
                    ) : (
                        <NavCollapsibleGroup
                            key={section.label}
                            label={section.label}
                            items={section.items}
                        />
                    ),
                )}
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
