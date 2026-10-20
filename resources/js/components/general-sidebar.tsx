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

/** Item menu + izin modul General yang dibutuhkan (kosong = semua yang masuk). */
export type ItemGeneral = NavItem & { izin?: string | string[] };

export type GeneralSection = { label: string; items: ItemGeneral[] };

const SUPER_ADMIN_SAJA = 'super-admin';

/**
 * Saring menu sesuai modul staf General. Super admin melihat semuanya;
 * `super-admin` menandai menu di luar workspace (area sekolah).
 */
export function saringMenuGeneral(
    items: ItemGeneral[],
    roles: string[],
    permissions: string[],
): ItemGeneral[] {
    if (roles.includes('SUPER_ADMIN')) {
        return items;
    }

    return items.filter((item) => {
        if (!item.izin) {
            return true;
        }

        const izin = Array.isArray(item.izin) ? item.izin : [item.izin];

        return izin.some(
            (i) => i !== SUPER_ADMIN_SAJA && permissions.includes(i),
        );
    });
}

/**
 * Menu workspace Absensi General (spec 009), dikelompokkan seperti sidebar
 * sekolah. "Haji (kartu lama)" memakai rute `/kartu-bebas/*` yang sudah ada:
 * hanya letak menunya yang pindah, data dan link scan kartu tercetak tetap.
 */
export const generalUtama: ItemGeneral[] = [
    {
        title: 'Ringkasan',
        href: '/general',
        icon: LayoutGrid,
        izin: 'general-ringkasan.access',
    },
    {
        title: 'Penyelenggara',
        href: '/general/penyelenggara',
        icon: Building2,
        izin: 'general-penyelenggara.access',
    },
    {
        title: 'Semua Kategori',
        href: '/general/kategori',
        icon: Shapes,
        izin: 'general-kategori.access',
    },
    {
        title: 'Semua Program',
        href: '/general/program',
        icon: CalendarRange,
        izin: 'general-program.access',
    },
    {
        title: 'Pengguna',
        href: '/general/pengguna',
        icon: UserCog,
        izin: [
            'general-pengguna-staf.access',
            'general-pengguna-penyelenggara.access',
        ],
    },
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
                        izin: 'general-haji-dashboard.access',
                    },
                    {
                        title: 'Peserta',
                        href: '/kartu-bebas/peserta',
                        icon: Users,
                        izin: 'general-haji-peserta.access',
                    },
                    {
                        title: 'Format Data',
                        href: '/kartu-bebas/data',
                        icon: Database,
                        izin: 'general-haji-format.access',
                    },
                    {
                        title: 'Absensi & Link Scan',
                        href: '/kartu-bebas/absensi',
                        icon: ClipboardCheck,
                        izin: 'general-haji-absensi.access',
                    },
                    {
                        title: 'Laporan Absensi',
                        href: '/kartu-bebas/laporan',
                        icon: FileSpreadsheet,
                        izin: 'general-haji-laporan.access',
                    },
                    {
                        title: 'Generate',
                        href: '/kartu-bebas/generate',
                        icon: Wand2,
                        izin: 'general-haji-generate.access',
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
                        izin: 'general-desain-frame.access',
                    },
                    {
                        title: 'Layout Kartu',
                        href: '/kartu-bebas/layouts',
                        icon: LayoutTemplate,
                        izin: 'general-desain-layout.access',
                    },
                    {
                        title: 'Riwayat Kartu',
                        href: '/kartu-bebas/riwayat',
                        icon: History,
                        izin: 'general-desain-riwayat.access',
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
                    izin: 'general-desain-frame.access',
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
                izin: 'general-pengaturan-umum.access',
            },
            {
                title: 'Google Drive',
                href: '/general/pengaturan#drive',
                icon: HardDrive,
                izin: 'general-pengaturan-drive.access',
            },
            {
                title: 'WhatsApp',
                href: '/general/pengaturan#whatsapp',
                icon: MessageSquare,
                izin: 'general-pengaturan-whatsapp.access',
            },
        ],
    },
    {
        label: 'Bantuan',
        items: [
            {
                title: 'Panduan',
                href: '/admin/panduan',
                icon: LifeBuoy,
                izin: SUPER_ADMIN_SAJA,
            },
        ],
    },
];

export function GeneralSidebar() {
    const konteks = useKonteksGeneral();
    const { app, auth } = usePage<{
        app: { workspace_name?: string | null };
        auth: { user: { roles?: string[]; permissions?: string[] } | null };
    }>().props;
    const roles = auth?.user?.roles ?? [];
    const permissions = auth?.user?.permissions ?? [];
    const superAdmin = roles.includes('SUPER_ADMIN');
    const saring = (items: ItemGeneral[]) =>
        saringMenuGeneral(items, roles, permissions);
    const tampil = (sections: GeneralSection[]) =>
        sections
            .map((section) => ({ ...section, items: saring(section.items) }))
            .filter((section) => section.items.length > 0);

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
                                        {superAdmin
                                            ? 'Workspace super admin'
                                            : 'Workspace staf'}
                                    </span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {saring(generalUtama).length > 0 && (
                    <NavGroup label="Utama" items={saring(generalUtama)} />
                )}
                {tampil(menuKonteks(konteks?.aktif ?? null)).map(
                    (section, i) =>
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
                {tampil(generalSections).map((section) => (
                    <NavCollapsibleGroup
                        key={section.label}
                        label={section.label}
                        items={section.items}
                    />
                ))}
            </SidebarContent>

            <SidebarFooter>
                {superAdmin && (
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
                )}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
