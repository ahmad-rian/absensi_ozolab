import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

export type BadgedNavItem = NavItem & { badge?: number };

interface NavGroupProps {
    label: string;
    items: BadgedNavItem[];
}

function NavLinks({ items }: { items: BadgedNavItem[] }) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <SidebarMenu>
            {items.map((item) => (
                <SidebarMenuItem key={item.title}>
                    <SidebarMenuButton
                        asChild
                        isActive={!item.newTab && isCurrentUrl(item.href)}
                        tooltip={{ children: item.title }}
                    >
                        {item.newTab ? (
                            <a href={item.href as string} target="_blank" rel="noopener noreferrer">
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </a>
                        ) : (
                            <Link href={item.href} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        )}
                    </SidebarMenuButton>
                    {item.badge ? <SidebarMenuBadge>{item.badge > 99 ? '99+' : item.badge}</SidebarMenuBadge> : null}
                </SidebarMenuItem>
            ))}
        </SidebarMenu>
    );
}

export function NavGroup({ label, items }: NavGroupProps) {
    if (items.length === 0) return null;

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{label}</SidebarGroupLabel>
            <NavLinks items={items} />
        </SidebarGroup>
    );
}

/**
 * Grup menu yang bisa dibuka-tutup.
 *
 * Grup yang salah satu anaknya sedang aktif otomatis terbuka, dan status
 * buka/tutup diingat di localStorage. Saat sidebar diciutkan jadi ikon,
 * grup dirender datar — `SidebarGroupLabel` menyembunyikan diri di mode itu,
 * jadi trigger-nya tidak akan bisa diklik.
 */
export function NavCollapsibleGroup({ label, items }: NavGroupProps) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { state } = useSidebar();

    const hasActiveChild = items.some((item) => !item.newTab && isCurrentOrParentUrl(item.href));
    const storageKey = `sidebar-group:${label}`;

    const [open, setOpen] = useState(() => readStoredOpen(storageKey) ?? hasActiveChild);

    // Buka sendiri saat pindah ke halaman yang ada di dalam grup ini.
    useEffect(() => {
        if (hasActiveChild) setOpen(true);
    }, [hasActiveChild]);

    function toggle(next: boolean) {
        setOpen(next);
        try {
            window.localStorage.setItem(storageKey, next ? '1' : '0');
        } catch {
            /* localStorage bisa ditolak di mode privat — abaikan */
        }
    }

    if (items.length === 0) return null;

    if (state === 'collapsed') {
        return (
            <SidebarGroup className="px-2 py-0">
                <NavLinks items={items} />
            </SidebarGroup>
        );
    }

    return (
        <Collapsible open={open} onOpenChange={toggle}>
            <SidebarGroup className="px-2 py-0">
                <SidebarGroupLabel asChild>
                    <CollapsibleTrigger className="hover:bg-sidebar-accent hover:text-sidebar-accent-foreground flex w-full items-center rounded-md transition-colors">
                        {label}
                        <ChevronRight className="ml-auto size-3.5 transition-transform duration-200 data-[state=open]:rotate-90" data-state={open ? 'open' : 'closed'} />
                    </CollapsibleTrigger>
                </SidebarGroupLabel>
                <CollapsibleContent>
                    <SidebarGroupContent>
                        <NavLinks items={items} />
                    </SidebarGroupContent>
                </CollapsibleContent>
            </SidebarGroup>
        </Collapsible>
    );
}

function readStoredOpen(key: string): boolean | null {
    if (typeof window === 'undefined') return null;

    try {
        const raw = window.localStorage.getItem(key);
        return raw === null ? null : raw === '1';
    } catch {
        return null;
    }
}

// Keep backward-compatible export
export function NavMain({ items = [] }: { items: NavItem[] }) {
    return <NavGroup label="Platform" items={items} />;
}
