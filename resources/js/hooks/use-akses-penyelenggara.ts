import { usePage } from '@inertiajs/react';

export type AksesPenyelenggara = {
    program: boolean;
    kelolaPeserta: boolean;
    kartu: boolean;
};

type AuthProps = {
    auth: { user: { roles?: string[]; permissions?: string[] } | null };
};

/**
 * Izin modul penyelenggara untuk menampilkan atau menyembunyikan tombol.
 * Server tetap menolak lewat middleware `permission:`; ini hanya supaya
 * pendamping tidak melihat tombol yang pasti berujung 403.
 */
export function aksesDari(
    roles: string[],
    permissions: string[],
): AksesPenyelenggara {
    const boleh = (izin: string) =>
        roles.includes('SUPER_ADMIN') || permissions.includes(izin);

    return {
        program: boleh('penyelenggara-program.access'),
        kelolaPeserta: boleh('penyelenggara-peserta-kelola.access'),
        kartu: boleh('penyelenggara-kartu.access'),
    };
}

export function useAksesPenyelenggara(): AksesPenyelenggara {
    const { auth } = usePage().props as unknown as AuthProps;

    return aksesDari(auth?.user?.roles ?? [], auth?.user?.permissions ?? []);
}
