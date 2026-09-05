import { router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, School } from 'lucide-react';
import { useState } from 'react';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { SidebarMenuButton } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';

type SchoolItem = {
    id: string;
    name: string;
    slug: string;
};

/**
 * Pemilih sekolah untuk SUPER_ADMIN, duduk di kepala sidebar.
 *
 * Menyembunyikan diri sendiri kalau daftarnya kosong atau tunggal:
 * HandleInertiaRequests hanya mengisi prop `schools` untuk super admin, jadi
 * role lain menerima [] dan tidak pernah melihat komponen ini.
 */
export function SchoolSwitcher() {
    const [open, setOpen] = useState(false);
    const { currentSchool, schools } = usePage().props as unknown as {
        currentSchool: { id: string; name: string; slug: string } | null;
        schools: SchoolItem[];
    };

    if (!schools || !Array.isArray(schools) || schools.length <= 1) return null;

    function switchSchool(schoolId: string) {
        setOpen(false);
        // Tanpa preserveScroll: rutenya membalas Inertia::location() yang memuat
        // ulang keras ke dashboard, jadi opsi itu tidak pernah berpengaruh.
        router.post('/admin/switch-school', { school_id: schoolId });
    }

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <SidebarMenuButton
                    size="lg"
                    className="w-full border bg-sidebar-accent/50 data-[state=open]:bg-sidebar-accent"
                >
                    <div className="bg-primary/10 text-primary flex size-8 items-center justify-center rounded-lg">
                        <School className="size-4" />
                    </div>
                    <div className="ml-1 grid flex-1 text-left text-sm leading-tight">
                        <span className="truncate font-semibold">{currentSchool?.name ?? 'Pilih Sekolah'}</span>
                        <span className="text-muted-foreground truncate text-xs">
                            {currentSchool?.slug ?? 'Belum dipilih'}
                        </span>
                    </div>
                    <ChevronsUpDown className="text-muted-foreground ml-auto size-4" />
                </SidebarMenuButton>
            </PopoverTrigger>
            <PopoverContent className="w-[--radix-popover-trigger-width] p-0" align="start">
                <Command>
                    <CommandInput placeholder="Cari sekolah..." />
                    <CommandList>
                        <CommandEmpty>Sekolah tidak ditemukan.</CommandEmpty>
                        <CommandGroup heading="Sekolah">
                            {schools.map((school) => (
                                <CommandItem key={school.id} value={school.name} onSelect={() => switchSchool(school.id)}>
                                    <Check
                                        className={cn('mr-2 size-4', currentSchool?.id === school.id ? 'opacity-100' : 'opacity-0')}
                                    />
                                    <div className="flex-1">
                                        <p className="text-sm font-medium">{school.name}</p>
                                        <p className="text-muted-foreground text-xs">{school.slug}</p>
                                    </div>
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
