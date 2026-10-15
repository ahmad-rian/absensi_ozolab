import { Head, Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import {
    KELAS_KARTU_KACA,
    LatarLangit,
} from '@/components/shared/latar-langit';
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
            <div className="relative isolate flex min-h-dvh items-center justify-center px-4">
                <LatarLangit />
                <div
                    className={`w-full max-w-md p-8 text-center ${KELAS_KARTU_KACA}`}
                >
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
