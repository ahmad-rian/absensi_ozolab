import { Head, Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes/penyelenggara';
import { index as kategoriIndex } from '@/routes/penyelenggara/kategori';
import {
    dashboard as programDashboard,
    index as programIndex,
} from '@/routes/penyelenggara/program';

type Props = {
    penyelenggara: {
        id: string;
        name: string;
        participant_quota: number | null;
    };
    programs: {
        id: string;
        name: string;
        kategori: string | null;
        peserta: number;
        hadir_hari_ini: number;
    }[];
};

export default function PenyelenggaraDashboard({
    penyelenggara,
    programs,
}: Props) {
    return (
        <>
            <Head title={`Beranda — ${penyelenggara.name}`} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {penyelenggara.name}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Absensi multi-kategori · kuota peserta{' '}
                        {penyelenggara.participant_quota ?? 'tanpa batas'}
                    </p>
                </div>

                {programs.length === 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Mulai dari sini
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-1 text-sm text-muted-foreground">
                            <p>
                                1. Buat{' '}
                                <Link
                                    href={kategoriIndex()}
                                    className="underline"
                                >
                                    kategori
                                </Link>{' '}
                                dari template (mis. Haji/Umroh).
                            </p>
                            <p>
                                2. Buat{' '}
                                <Link
                                    href={programIndex()}
                                    className="underline"
                                >
                                    program
                                </Link>
                                , susun grup, impor peserta, atur jadwal, lalu
                                bagikan link scan.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {programs.map((p) => (
                            <Link
                                key={p.id}
                                href={programDashboard(p.id)}
                                className="rounded-xl border p-4 hover:bg-muted/40"
                            >
                                <div className="text-sm text-muted-foreground">
                                    {p.kategori}
                                </div>
                                <div className="font-semibold">{p.name}</div>
                                <div className="mt-3 text-2xl font-semibold tabular-nums">
                                    {p.hadir_hari_ini}
                                    <span className="text-base font-normal text-muted-foreground">
                                        /{p.peserta} hadir hari ini
                                    </span>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

PenyelenggaraDashboard.layout = {
    breadcrumbs: [{ title: 'Beranda', href: dashboard() }],
};
