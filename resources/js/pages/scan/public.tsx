import { Head } from '@inertiajs/react';
import { PublicScanConsole, type ScanSchool } from '@/components/scanner/public-scan-console';

type PageProps = {
    school: ScanSchool;
    scanToken: string;
};

export default function PublicScanPage({ school, scanToken }: PageProps) {
    return (
        <>
            <Head title={`Scan Absensi — ${school.name}`} />
            <PublicScanConsole school={school} scanUrl={`/scan/${scanToken}`} tagline="Absensi Digital" />
        </>
    );
}
