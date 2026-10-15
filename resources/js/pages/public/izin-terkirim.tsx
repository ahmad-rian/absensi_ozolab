import { Head, Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/izin';

/** Konfirmasi setelah pengajuan izin publik terkirim. */
export default function IzinTerkirim({
    info,
}: {
    info: { nama: string; sekolah: string };
}) {
    return (
        <>
            <Head title="Pengajuan terkirim" />
            <div className="flex min-h-dvh items-center justify-center bg-[linear-gradient(180deg,#EAF3FB_0%,#FFFFFF_100%)] px-4 dark:bg-[linear-gradient(180deg,#0B1626_0%,#0F1B2D_100%)]">
                <div className="w-full max-w-md rounded-2xl border bg-card p-8 text-center shadow-sm">
                    <CheckCircle2 className="mx-auto size-12 text-emerald-600" />
                    <h1 className="mt-4 text-xl font-bold tracking-tight">
                        Pengajuan terkirim
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Pengajuan untuk{' '}
                        <span className="font-medium text-foreground">
                            {info.nama}
                        </span>{' '}
                        sudah diterima {info.sekolah}. Sekolah akan meninjaunya;
                        bila disetujui, absensi anak tercatat izin/sakit.
                    </p>
                    <Button asChild variant="outline" className="mt-6">
                        <Link href={index()}>Ajukan untuk anak lain</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
