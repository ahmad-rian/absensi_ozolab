import { Head, usePage } from '@inertiajs/react';
import { PublicScanConsole } from '@/components/scanner/public-scan-console';

/**
 * Konsol scan kamera program penyelenggara (spec 004). Datang/pulang diputuskan
 * server dari jadwal sesi — tidak ada tombol mode.
 */
export default function ProgramScanner({
    program,
    scanUrl,
    riwayatUrl,
    tagline = 'Absensi peserta · datang & pulang mengikuti jadwal sesi',
    pesanMati = 'Program ini sudah diarsip.',
}: {
    program: { name: string; aktif: boolean };
    scanUrl: string;
    riwayatUrl: string;
    /** Dipakai juga oleh halaman kamera Haji (`/p/{kode}/kamera`). */
    tagline?: string;
    pesanMati?: string;
}) {
    const { app } = usePage<{ app: { logo?: string | null } }>().props;

    return (
        <>
            <Head title={`Scan — ${program.name}`} />
            <PublicScanConsole
                subjectLabel="peserta"
                groupLabel="Sesi"
                school={{
                    name: program.name,
                    logo_url: app?.logo ?? null,
                    is_active: program.aktif,
                }}
                scanUrl={scanUrl}
                riwayatUrl={riwayatUrl}
                tagline={tagline}
                hint="Arahkan QR kartu peserta ke kamera, atau gunakan scanner gun."
                disabledNotice={program.aktif ? null : pesanMati}
            />
        </>
    );
}
