import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes/penyelenggara';

type Props = {
    penyelenggara: {
        id: string;
        name: string;
        participant_quota: number | null;
    };
};

export default function PenyelenggaraDashboard({ penyelenggara }: Props) {
    return (
        <>
            <Head title={`Beranda — ${penyelenggara.name}`} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {penyelenggara.name}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Absensi multi-kategori: haji/umroh, pelatihan,
                        pengajian, karyawan, dan event.
                    </p>
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Mulai dari sini
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="text-sm text-muted-foreground">
                        Kategori, program, peserta, dan jadwal akan muncul di
                        menu ini begitu modulnya diaktifkan. Kuota peserta:{' '}
                        <span className="font-medium text-foreground">
                            {penyelenggara.participant_quota ?? 'tanpa batas'}
                        </span>
                        .
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PenyelenggaraDashboard.layout = {
    breadcrumbs: [{ title: 'Beranda', href: dashboard() }],
};
