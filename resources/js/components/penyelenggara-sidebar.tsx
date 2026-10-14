import { Link, router, usePage } from '@inertiajs/react';
import { CalendarRange, LayoutGrid, Shapes, Users } from 'lucide-react';
import BrandLogo from '@/components/brand-logo';
import { NavGroup } from '@/components/nav-main';
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
import { dashboard, switchMethod } from '@/routes/penyelenggara';
import { index as kategori } from '@/routes/penyelenggara/kategori';
import { index as program } from '@/routes/penyelenggara/program';
import { index as tim } from '@/routes/penyelenggara/tim';
import type { NavItem } from '@/types';

type GuardedItem = NavItem & { permission: string };

type PageProps = {
    auth: { user: { roles?: string[]; permissions?: string[] } | null };
    currentPenyelenggara: { id: string; name: string } | null;
    penyelenggaras: { id: string; name: string; is_active: boolean }[];
};

export const penyelenggaraItems: GuardedItem[] = [
    {
        title: 'Beranda',
        href: dashboard.url(),
        icon: LayoutGrid,
        permission: 'penyelenggara-beranda.access',
    },
    {
        title: 'Kategori',
        href: kategori.url(),
        icon: Shapes,
        permission: 'penyelenggara-kategori.access',
    },
    {
        title: 'Program',
        href: program.url(),
        icon: CalendarRange,
        permission: 'penyelenggara-program.access',
    },
    {
        title: 'Tim',
        href: tim.url(),
        icon: Users,
        permission: 'penyelenggara-tim.access',
    },
];

/**
 * Menu yang boleh dilihat: super admin melewati izin (sama dengan
 * Gate::before); peran lain hanya modul yang diberikan.
 */
export function visiblePenyelenggaraItems(
    items: GuardedItem[],
    roles: string[],
    permissions: string[],
): GuardedItem[] {
    const isSuperAdmin = roles.includes('SUPER_ADMIN');

    return items.filter(
        (item) => isSuperAdmin || permissions.includes(item.permission),
    );
}

export function PenyelenggaraSidebar() {
    const { auth, currentPenyelenggara, penyelenggaras } = usePage()
        .props as unknown as PageProps;
    const roles = auth?.user?.roles ?? [];
    const isSuperAdmin = roles.includes('SUPER_ADMIN');

    const items = visiblePenyelenggaraItems(
        penyelenggaraItems,
        roles,
        auth?.user?.permissions ?? [],
    );

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard.url()} prefetch>
                                <BrandLogo className="size-8 rounded-lg" />
                                <div className="grid flex-1 text-left text-sm leading-tight">
                                    <span className="truncate font-semibold">
                                        {currentPenyelenggara?.name ??
                                            'Penyelenggara'}
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        Absensi multi-kategori
                                    </span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                {isSuperAdmin && penyelenggaras.length > 0 && (
                    <label className="grid gap-1 px-2 text-xs text-muted-foreground group-data-[collapsible=icon]:hidden">
                        Buka penyelenggara
                        <select
                            aria-label="Pilih penyelenggara"
                            className="h-8 rounded-md border bg-background px-2 text-sm text-foreground"
                            value={currentPenyelenggara?.id ?? ''}
                            onChange={(e) =>
                                router.post(switchMethod.url(), {
                                    penyelenggara_id: e.target.value,
                                })
                            }
                        >
                            <option value="" disabled>
                                Pilih…
                            </option>
                            {penyelenggaras.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                    {p.is_active ? '' : ' (nonaktif)'}
                                </option>
                            ))}
                        </select>
                    </label>
                )}
            </SidebarHeader>

            <SidebarContent>
                {currentPenyelenggara && (
                    <NavGroup label="Utama" items={items} />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
