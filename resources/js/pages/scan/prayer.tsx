import { Head } from '@inertiajs/react';
import { PublicScanConsole, type ScanSchool } from '@/components/scanner/public-scan-console';

type PrayerWindow = {
    enabled: boolean;
    start: string;
    end: string;
    type: string;
    type_label: string;
    type_short: string;
    window: string;
};

type PageProps = {
    school: ScanSchool;
    scanToken: string;
    prayerSchedule: {
        any_enabled: boolean;
        enabled_windows: PrayerWindow[];
        windows_label: string | null;
    };
};

export default function PrayerScanPage({ school, scanToken, prayerSchedule }: PageProps) {
    const active = prayerSchedule.enabled_windows;

    // Satu tautan melayani Dhuha maupun Dzuhur — jenisnya ditentukan server
    // dari jam scan, jadi petugas mushola tidak perlu memilih mode apa pun.
    const tagline = active.length === 1 ? active[0].type_label : 'Absen Sholat';

    const hint = prayerSchedule.any_enabled
        ? `${active.map((w) => `${w.type_short} ${w.window}`).join(' · ')} · sekali scan per jenis`
        : '';

    return (
        <>
            <Head title={`Absen Sholat — ${school.name}`} />
            <PublicScanConsole
                school={school}
                scanUrl={`/scan/${scanToken}/sholat`}
                tagline={tagline}
                hint={hint}
                disabledNotice={
                    prayerSchedule.any_enabled ? null : 'Absen sholat belum diaktifkan untuk sekolah ini.'
                }
            />
        </>
    );
}
