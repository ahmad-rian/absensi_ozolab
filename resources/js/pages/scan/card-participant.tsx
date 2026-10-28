import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { PublicScanConsole } from '@/components/scanner/public-scan-console';
import { Button } from '@/components/ui/button';
import { riwayat, scan } from '@/routes/public/card-scanner';

export default function CardParticipantScanner({
    layout,
}: {
    layout: { name: string; token: string };
}) {
    const [mode, setMode] = useState<'masuk' | 'pulang'>('masuk');
    const { app } = usePage<{ app: { logo?: string | null } }>().props;

    return (
        <>
            <Head title={`Scan peserta — ${layout.name}`} />
            <div
                className="flex justify-center gap-3 bg-slate-950 p-3 text-white"
                aria-label="Mode absensi"
            >
                <Button
                    variant={mode === 'masuk' ? 'default' : 'secondary'}
                    aria-pressed={mode === 'masuk'}
                    onClick={() => setMode('masuk')}
                >
                    Masuk / Hadir
                </Button>
                <Button
                    variant={mode === 'pulang' ? 'default' : 'secondary'}
                    aria-pressed={mode === 'pulang'}
                    onClick={() => setMode('pulang')}
                >
                    Pulang
                </Button>
            </div>
            <PublicScanConsole
                subjectLabel="peserta"
                groupLabel="Layout"
                // Remount per mode supaya kamera memakai endpoint mode yang
                // baru; riwayat tetap karena dibaca ulang dari server.
                key={mode}
                school={{
                    name: layout.name,
                    logo_url: app?.logo ?? null,
                    is_active: true,
                }}
                scanUrl={scan.url({ token: layout.token, mode })}
                riwayatUrl={riwayat.url({ token: layout.token })}
                tagline={`Absensi peserta · ${mode === 'masuk' ? 'Masuk / Hadir' : 'Pulang'}`}
                hint="Gunakan QR absensi peserta. Boleh scan berkali-kali: semua scan masuk riwayat, jam masuk tetap scan pertama, jam pulang ikut scan terakhir."
            />
        </>
    );
}
