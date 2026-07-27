import { Head, Link } from '@inertiajs/react';
import { MoonStar, Settings } from 'lucide-react';
import { QrScanner } from '@/components/scanner/qr-scanner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

type Props = {
    prayer: { enabled: boolean; start: string; end: string; all_religions: boolean } | null;
};

export default function ScannerPrayer({ prayer }: Props) {
    return (
        <>
            <Head title="Scanner Absen Sholat" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="text-center">
                    <h1 className="flex items-center justify-center gap-2 text-2xl font-bold tracking-tight">
                        <MoonStar className="size-6 text-emerald-600" />
                        Scanner Absen Sholat
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Terpisah dari absensi sekolah. Sekali scan per siswa per hari.
                    </p>
                </div>

                {!prayer?.enabled ? (
                    <Card className="mx-auto w-full max-w-2xl">
                        <CardHeader>
                            <CardTitle>Absen sholat belum aktif</CardTitle>
                            <CardDescription>
                                Nyalakan dulu di Pengaturan Sekolah, lengkap dengan jam mulai dan selesai.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button asChild variant="outline">
                                <Link href="/admin/pengaturan">
                                    <Settings className="mr-2 size-4" />
                                    Buka Pengaturan
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="mx-auto w-full max-w-2xl space-y-4">
                        <p className="text-muted-foreground text-center text-sm">
                            Jendela absen: <span className="font-semibold">{prayer.start} – {prayer.end}</span>
                            {prayer.all_religions ? ' · semua siswa' : ' · hanya siswa beragama Islam'}
                        </p>
                        <QrScanner scanEndpoint="/admin/scanner/sholat/scan" />
                    </div>
                )}
            </div>
        </>
    );
}

ScannerPrayer.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Scanner Absen Sholat', href: '/admin/scanner/sholat' },
    ],
};
