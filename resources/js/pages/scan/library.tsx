import { Head } from '@inertiajs/react';
import type { ScanSchool } from '@/components/scanner/public-scan-console';
import { PublicScanConsole } from '@/components/scanner/public-scan-console';

type PageProps = {
    school: ScanSchool;
    scanToken: string;
    featureEnabled: boolean;
};

export default function LibraryScanPage({ school, scanToken, featureEnabled }: PageProps) {
    return (
        <>
            <Head title={`Kunjungan Perpustakaan — ${school.name}`} />
            <PublicScanConsole
                school={school}
                scanUrl={`/scan/${scanToken}/perpustakaan`}
                tagline="Kunjungan Perpustakaan"
                // Satu tautan melayani masuk maupun keluar — server yang tahu
                // siswa itu sedang di dalam atau tidak, jadi petugas tidak
                // pernah perlu memilih mode.
                hint="Tempel sekali saat masuk, tempel lagi saat keluar"
                disabledNotice={featureEnabled ? null : 'Kunjungan perpustakaan belum diaktifkan untuk sekolah ini.'}
            />
        </>
    );
}
