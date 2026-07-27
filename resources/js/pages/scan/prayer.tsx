import { Head } from '@inertiajs/react';
import { PublicScanConsole, type ScanSchool } from '@/components/scanner/public-scan-console';

type PageProps = {
    school: ScanSchool;
    scanToken: string;
    prayer: { enabled: boolean; start: string; end: string; all_religions: boolean };
};

export default function PrayerScanPage({ school, scanToken, prayer }: PageProps) {
    return (
        <>
            <Head title={`Absen Sholat — ${school.name}`} />
            <PublicScanConsole
                school={school}
                scanUrl={`/scan/${scanToken}/sholat`}
                tagline="Absen Sholat Dzuhur"
                hint={`Dibuka pukul ${prayer.start} – ${prayer.end} · sekali scan per hari`}
                disabledNotice={
                    prayer.enabled ? null : 'Absen sholat belum diaktifkan untuk sekolah ini.'
                }
            />
        </>
    );
}
