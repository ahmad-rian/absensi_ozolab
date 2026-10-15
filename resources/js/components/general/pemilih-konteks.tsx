import { router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, Layers } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { konteks as konteksRoute } from '@/routes/general';

export type KonteksAktif = {
    jenis: 'haji' | 'program';
    id: string;
    label: string;
    kategori: string | null;
    kategori_id: string | null;
    program_id: string | null;
    penyelenggara: string | null;
};

export type KonteksGeneral = {
    aktif: KonteksAktif;
    opsi: { grup: string; opsi: { id: string; label: string }[] }[];
};

export function useKonteksGeneral(): KonteksGeneral | null {
    return (
        (usePage().props as { konteksGeneral?: KonteksGeneral | null })
            .konteksGeneral ?? null
    );
}

/**
 * Pemilih kategori/program di kanan atas workspace general — seperti pemilih
 * cabang. Menu sidebar mengikuti pilihan ini.
 */
export function PemilihKonteks() {
    const konteks = useKonteksGeneral();

    if (!konteks) {
        return null;
    }

    const { aktif, opsi } = konteks;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    className="h-9 max-w-[16rem] gap-2 px-3"
                >
                    <Layers className="size-4 shrink-0 text-primary" />
                    <span className="truncate text-left">
                        {aktif.kategori ? (
                            <>
                                <span className="text-muted-foreground">
                                    {aktif.kategori} ·{' '}
                                </span>
                                {aktif.label}
                            </>
                        ) : (
                            aktif.label
                        )}
                    </span>
                    <ChevronsUpDown className="size-3.5 shrink-0 text-muted-foreground" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="max-h-[70vh] w-72 overflow-y-auto"
            >
                {opsi.map((grup, i) => (
                    <DropdownMenuGroup key={grup.grup}>
                        {i > 0 && <DropdownMenuSeparator />}
                        <DropdownMenuLabel className="text-xs font-medium text-muted-foreground">
                            {grup.grup}
                        </DropdownMenuLabel>
                        {grup.opsi.map((o) => (
                            <DropdownMenuItem
                                key={o.id}
                                onSelect={() =>
                                    o.id !== aktif.id &&
                                    router.post(konteksRoute.url(), {
                                        konteks: o.id,
                                    })
                                }
                                className="justify-between"
                            >
                                <span className="truncate">{o.label}</span>
                                {o.id === aktif.id && (
                                    <Check className="size-4 text-primary" />
                                )}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuGroup>
                ))}
                {opsi.length === 1 && (
                    <p className="px-2 py-2 text-xs text-muted-foreground">
                        Program dari penyelenggara akan muncul di sini setelah
                        dibuat.
                    </p>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
