import { Head } from '@inertiajs/react';
import { PublicScanConsole, type ScanSchool } from '@/components/scanner/public-scan-console';

type JendelaSholat = {
    label: string;
    jam: string;
    aktif: boolean;
};

type PageProps = {
    school: ScanSchool;
    scanToken: string;
    featureEnabled: boolean;
    jendelaSholat: JendelaSholat[];
};

/**
 * Satu gerbang mencatat datang, Dhuha, Dzuhur, dan pulang — server yang memilih
 * mana berdasarkan jam dan apa yang belum tercatat. Tanpa baris ini operator
 * tidak punya cara tahu kenapa kartu yang sama menghasilkan catatan berbeda di
 * jam yang berbeda.
 */
function petunjuk(jendela: JendelaSholat[]): string | undefined {
    if (jendela.length === 0) {
        return undefined;
    }

    return jendela
        .map((w) => `${w.label} ${w.jam}${w.aktif ? ' · sedang dibuka' : ''}`)
        .join(' · ');
}

export default function PublicScanPage({ school, scanToken, featureEnabled, jendelaSholat }: PageProps) {
    return (
        <>
            <Head title={`Scan Absensi — ${school.name}`} />
            <PublicScanConsole
                school={school}
                scanUrl={`/scan/${scanToken}`}
                tagline="Absensi Digital"
                hint={petunjuk(jendelaSholat)}
                disabledNotice={featureEnabled ? null : 'Absensi sekolah sedang dimatikan oleh admin.'}
            />
        </>
    );
}
