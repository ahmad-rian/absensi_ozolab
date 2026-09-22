import { Link } from '@inertiajs/react';
import { Home } from 'lucide-react';
import { NavGroup } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { logout } from '@/routes';
import { index } from '@/routes/orangtua';
export function OrangtuaSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <Link href={index()} className="p-3 font-semibold">
                    Portal Orang Tua
                </Link>
            </SidebarHeader>
            <SidebarContent>
                <NavGroup
                    label="Keluarga"
                    items={[{ title: 'Anak saya', href: index(), icon: Home }]}
                />
            </SidebarContent>
            <SidebarFooter>
                <Link
                    href={logout()}
                    method="post"
                    as="button"
                    className="rounded-lg p-3 text-left"
                >
                    Keluar
                </Link>
            </SidebarFooter>
        </Sidebar>
    );
}
