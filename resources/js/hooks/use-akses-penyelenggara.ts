import { usePage } from '@inertiajs/react';

export type AksesPenyelenggara = {
    program: boolean;
    kelolaPeserta: boolean;
    kartu: boolean;
};

type AuthProps = {
    auth: {
        user: {
            roles?: string[];
            permissions?: string[];
            lintas_penyelenggara?: boolean;
            penyelenggara_penuh?: boolean;
        } | null;
    };
};

/**
 * Izin modul penyelenggara untuk menampilkan atau menyembunyikan tombol.
 * Server tetap menolak lewat middleware `permission:`; ini hanya supaya
 * pendamping tidak melihat tombol yang pasti berujung 403.
 */
export function aksesDari(
    roles: string[],
    permissions: string[],
    penyelenggaraPenuh = false,
    lintasPenyelenggara = false,
): AksesPenyelenggara {
    // Staf General: modul Penyelenggara = penuh; modul Kategori & Program
    // hanya program (server: User::aksesPenyelenggaraLewatGeneral).
    const boleh = (izin: string) =>
        penyelenggaraPenuh ||
        roles.includes('SUPER_ADMIN') ||
        permissions.includes(izin) ||
        (lintasPenyelenggara && izin === 'penyelenggara-program.access');

    return {
        program: boleh('penyelenggara-program.access'),
        kelolaPeserta: boleh('penyelenggara-peserta-kelola.access'),
        kartu: boleh('penyelenggara-kartu.access'),
    };
}

export function useAksesPenyelenggara(): AksesPenyelenggara {
    const { auth } = usePage().props as unknown as AuthProps;

    return aksesDari(
        auth?.user?.roles ?? [],
        auth?.user?.permissions ?? [],
        auth?.user?.penyelenggara_penuh ?? false,
        auth?.user?.lintas_penyelenggara ?? false,
    );
}
