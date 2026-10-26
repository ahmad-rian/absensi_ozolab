import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, ClipboardCheck, Users } from 'lucide-react';
import { useState } from 'react';
import { show } from '@/actions/App/Http/Controllers/Admin/AbsensiKelasController';
import { DaftarSiswaKelas } from '@/components/absensi-kelas/daftar-siswa-kelas';
import { FormAbsenKelas } from '@/components/absensi-kelas/form-absen-kelas';
import { RekapBulananKelas } from '@/components/absensi-kelas/rekap-bulanan-kelas';
import type {
    KelasRingkas,
    RekapBulanan,
    SiswaKelas,
} from '@/components/absensi-kelas/types';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { dashboard } from '@/routes';

type Tab = 'absen' | 'rekap' | 'siswa';

type PageProps = {
    kelas: KelasRingkas;
    daftarKelas: { id: string; name: string }[];
    tanggal: string;
    bulan: string;
    tab: Tab;
    siswa: SiswaKelas[];
    rekap?: RekapBulanan;
    flash?: { success?: string; error?: string };
    errors: Record<string, string>;
};

/**
 * Halaman detail satu kelas, seperti ruang kelas di LMS: absen manual hari
 * ini, daftar hadir sebulan, dan anggota kelas — semuanya di satu tempat.
 */
export default function DetailKelas() {
    const {
        kelas,
        daftarKelas,
        tanggal,
        bulan,
        tab,
        siswa,
        rekap,
        flash,
        errors,
    } = usePage<PageProps>().props;
    const bulanIni = new Date().toISOString().slice(0, 7);

    const [tabAktif, setTabAktif] = useState<string>(tab);

    const [tabServer, setTabServer] = useState<string>(tab);

    // Server bisa memindah tab (mis. klik sel rekap membuka tab Absen).
    if (tab !== tabServer) {
        setTabServer(tab);
        setTabAktif(tab);
    }

    function gantiTab(tujuan: string) {
        setTabAktif(tujuan);
        // Tab ikut tercatat di URL supaya bisa dibagikan / dimuat ulang.
        window.history.replaceState(
            window.history.state,
            '',
            show.url(kelas.id, { query: { tanggal, bulan, tab: tujuan } }),
        );
    }

    function gantiKelas(id: string) {
        router.get(show.url(id, { query: { tanggal, bulan, tab } }));
    }

    return (
        <>
            <Head title={`Kelas ${kelas.name}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <Link
                            href="/admin/kelas"
                            className="mb-1 inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
                        >
                            <ArrowLeft className="size-4" /> Data Kelas
                        </Link>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight">
                                Kelas {kelas.name}
                            </h1>
                            {kelas.grade_level && (
                                <Badge variant="secondary">
                                    Angkatan {kelas.grade_level}
                                </Badge>
                            )}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {siswa.length} siswa aktif · Wali kelas:{' '}
                            {kelas.wali ?? 'belum diatur'}
                        </p>
                    </div>
                    <Select value={kelas.id} onValueChange={gantiKelas}>
                        <SelectTrigger className="sm:w-48">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {daftarKelas.map((k) => (
                                <SelectItem key={k.id} value={k.id}>
                                    Kelas {k.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {flash?.success && (
                    <div className="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-300">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-300">
                        {flash.error}
                    </div>
                )}

                <Tabs value={tabAktif} onValueChange={gantiTab}>
                    <TabsList>
                        <TabsTrigger value="absen">
                            <ClipboardCheck className="mr-1.5 size-4" /> Absen
                        </TabsTrigger>
                        <TabsTrigger value="rekap">
                            <CalendarDays className="mr-1.5 size-4" /> Rekap
                            Bulanan
                        </TabsTrigger>
                        <TabsTrigger value="siswa">
                            <Users className="mr-1.5 size-4" /> Siswa
                        </TabsTrigger>
                    </TabsList>
                    <TabsContent value="absen">
                        <FormAbsenKelas
                            key={`${kelas.id}-${tanggal}`}
                            kelas={kelas}
                            tanggal={tanggal}
                            siswa={siswa}
                            errors={errors}
                        />
                    </TabsContent>
                    <TabsContent value="rekap">
                        <RekapBulananKelas
                            kelasId={kelas.id}
                            bulan={bulan}
                            bulanIni={bulanIni}
                            rekap={rekap}
                        />
                    </TabsContent>
                    <TabsContent value="siswa">
                        <DaftarSiswaKelas siswa={siswa} />
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}

DetailKelas.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Kelas', href: '/admin/kelas' },
        { title: 'Detail Kelas', href: '#' },
    ],
};
