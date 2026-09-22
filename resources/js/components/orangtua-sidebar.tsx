import { Link, router, usePage } from '@inertiajs/react';
import {
    CalendarCheck,
    ChevronsUpDown,
    FileText,
    Images,
    LayoutDashboard,
    LogOut,
    Moon,
    Users,
} from 'lucide-react';
import { NavGroup } from '@/components/nav-main';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { logout } from '@/routes';
import { absensi, galeri, index, laporan, sholat } from '@/routes/orangtua';

type Anak = {
    id: string;
    name: string;
    classroom: string | null;
    photo: string | null;
};

/**
 * Anak yang aktif ikut di setiap tautan menu.
 *
 * Tanpa ini, berpindah dari Absensi ke Sholat akan diam-diam kembali ke anak
 * pertama — cacat yang hanya terasa oleh keluarga dengan lebih dari satu anak,
 * yaitu keluarga yang paling membutuhkan pemilihnya bekerja.
 */
function withAnak(url: string, anak: string | null): string {
    return anak ? `${url}?anak=${anak}` : url;
}

export function OrangtuaSidebar() {
    const { props } = usePage<{
        student: Anak | null;
        daftarAnak: Anak[];
        fiturSholat: boolean;
    }>();
    const aktif = props.student?.id ?? null;
    const daftar = props.daftarAnak ?? [];

    const menu = [
        {
            title: 'Beranda',
            href: withAnak(index().url, aktif),
            icon: LayoutDashboard,
        },
        {
            title: 'Absensi Sekolah',
            href: withAnak(absensi().url, aktif),
            icon: CalendarCheck,
        },
        ...(props.fiturSholat
            ? [
                  {
                      title: 'Absen Sholat',
                      href: withAnak(sholat().url, aktif),
                      icon: Moon,
                  },
              ]
            : []),
        {
            title: 'Laporan',
            href: withAnak(laporan().url, aktif),
            icon: FileText,
        },
        {
            title: 'Foto & Kartu',
            href: withAnak(galeri().url, aktif),
            icon: Images,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="gap-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            className="h-12"
                            tooltip="Portal Orang Tua"
                        >
                            <Link
                                href={withAnak(index().url, aktif)}
                                aria-label="Portal Orang Tua"
                            >
                                <Users className="size-5 shrink-0 text-sidebar-primary" />
                                <span className="truncate text-base font-bold tracking-tight group-data-[collapsible=icon]:hidden">
                                    Portal Orang Tua
                                </span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                {daftar.length > 1 && (
                    <PemilihAnak daftar={daftar} aktif={props.student} />
                )}
            </SidebarHeader>

            <SidebarContent className="[&_[data-active=true]]:bg-sidebar-primary [&_[data-active=true]]:font-semibold [&_[data-active=true]]:text-sidebar-primary-foreground">
                <NavGroup label="Menu" items={menu} />
            </SidebarContent>

            <SidebarFooter>
                {props.student && (
                    <div className="px-2 pb-1 text-xs text-muted-foreground group-data-[collapsible=icon]:hidden">
                        {props.student.name}
                        {props.student.classroom
                            ? ` · ${props.student.classroom}`
                            : ''}
                    </div>
                )}
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild tooltip="Keluar">
                            <Link
                                href={logout()}
                                method="post"
                                as="button"
                                aria-label="Keluar"
                            >
                                <LogOut className="size-4" />
                                <span className="group-data-[collapsible=icon]:hidden">
                                    Keluar
                                </span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
        </Sidebar>
    );
}

/**
 * Hanya muncul kalau anaknya lebih dari satu. Untuk satu anak pemilih ini
 * tidak memilih apa pun — ia cuma kotak yang harus dilewati.
 */
function PemilihAnak({
    daftar,
    aktif,
}: {
    daftar: Anak[];
    aktif: Anak | null;
}) {
    function pilih(id: string) {
        const url = new URL(window.location.href);
        url.searchParams.set('anak', id);
        router.get(url.pathname + url.search, {}, { preserveState: false });
    }

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    className="h-auto w-full justify-between gap-2 px-2 py-2 group-data-[collapsible=icon]:hidden"
                >
                    <span className="flex min-w-0 items-center gap-2">
                        {aktif?.photo ? (
                            <img
                                src={aktif.photo}
                                alt=""
                                className="size-7 shrink-0 rounded-md object-cover"
                            />
                        ) : (
                            <span className="size-7 shrink-0 rounded-md bg-muted" />
                        )}
                        <span className="min-w-0 text-left">
                            <span className="block truncate text-sm font-medium">
                                {aktif?.name ?? 'Pilih anak'}
                            </span>
                            <span className="block truncate text-xs text-muted-foreground">
                                {aktif?.classroom ?? '—'}
                            </span>
                        </span>
                    </span>
                    <ChevronsUpDown className="size-4 shrink-0 text-muted-foreground" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-64 p-0" align="start">
                <Command>
                    <CommandInput placeholder="Cari nama anak..." />
                    <CommandList>
                        <CommandEmpty>Tidak ditemukan.</CommandEmpty>
                        <CommandGroup>
                            {daftar.map((anak) => (
                                <CommandItem
                                    key={anak.id}
                                    value={anak.name}
                                    onSelect={() => pilih(anak.id)}
                                    className="gap-2"
                                >
                                    {anak.photo ? (
                                        <img
                                            src={anak.photo}
                                            alt=""
                                            className="size-7 rounded-md object-cover"
                                        />
                                    ) : (
                                        <span className="size-7 rounded-md bg-muted" />
                                    )}
                                    <span className="min-w-0">
                                        <span className="block truncate text-sm">
                                            {anak.name}
                                        </span>
                                        <span className="block truncate text-xs text-muted-foreground">
                                            {anak.classroom ?? '—'}
                                        </span>
                                    </span>
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
