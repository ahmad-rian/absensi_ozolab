import { Head, usePage } from '@inertiajs/react';
import { BookOpen, Search } from 'lucide-react';
import { useMemo, useState, type ReactNode } from 'react';
import { GUIDE_CHAPTERS, type GuideTopic } from '@/components/panduan/guide-registry';
import { AbsenSholat, AbsensiHarian, ImporSiswa, KenaikanKelas, SekolahBaru, TahunAjaran } from '@/components/panduan/sections/alur';
import { DaftarFitur, KeluhanUmum, TeksOrangTua } from '@/components/panduan/sections/lainnya';
import {
    ModulAbsensi,
    ModulDrive,
    ModulGateway,
    ModulJadwal,
    ModulKartuAlbum,
    ModulKartuBebas,
    ModulKelas,
    ModulLaporan,
    ModulNotifikasi,
    ModulOrangTua,
    ModulPengaturan,
    ModulPengguna,
    ModulRole,
    ModulSekolah,
    ModulSiswa,
    ModulWhatsapp,
} from '@/components/panduan/sections/modul';
import { ApaIni, LangkahPertama } from '@/components/panduan/sections/mulai';
import { PageHeader } from '@/components/shared/page-header';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import type { SchoolFeatureKey, SchoolFeatureMap } from '@/types';

/** Isi tiap topik dipetakan lewat id-nya, supaya registri tetap murni data. */
const CONTENT: Record<string, ReactNode> = {
    'apa-ini': <ApaIni />,
    'langkah-pertama': <LangkahPertama />,
    'sekolah-baru': <SekolahBaru />,
    'tahun-ajaran': <TahunAjaran />,
    'impor-siswa': <ImporSiswa />,
    'kenaikan-kelas': <KenaikanKelas />,
    'absensi-harian': <AbsensiHarian />,
    'absen-sholat': <AbsenSholat />,
    siswa: <ModulSiswa />,
    'orang-tua': <ModulOrangTua />,
    kelas: <ModulKelas />,
    'jadwal-absensi': <ModulJadwal />,
    absensi: <ModulAbsensi />,
    laporan: <ModulLaporan />,
    notifikasi: <ModulNotifikasi />,
    'kartu-album': <ModulKartuAlbum />,
    pengguna: <ModulPengguna />,
    drive: <ModulDrive />,
    whatsapp: <ModulWhatsapp />,
    pengaturan: <ModulPengaturan />,
    sekolah: <ModulSekolah />,
    role: <ModulRole />,
    gateway: <ModulGateway />,
    'kartu-bebas': <ModulKartuBebas />,
    'daftar-fitur': <DaftarFitur />,
    'keluhan-umum': <KeluhanUmum />,
    'teks-orang-tua': <TeksOrangTua />,
};

export default function PanduanIndex() {
    const { auth, features } = usePage().props as unknown as {
        auth: { user: { roles?: string[]; permissions?: string[] } | null };
        features?: Partial<SchoolFeatureMap>;
    };

    const [query, setQuery] = useState('');

    const isSuperAdmin = (auth?.user?.roles ?? []).includes('SUPER_ADMIN');
    const granted = auth?.user?.permissions ?? [];

    const chapters = useMemo(() => {
        // Aturan penyaringan PERSIS sama dengan app-sidebar.tsx: super admin
        // melompati cek permission tapi TIDAK melompati cek fitur, sejalan
        // dengan middleware `feature:`.
        const featureOn = (key?: SchoolFeatureKey) =>
            key === undefined || features === undefined || features[key] !== false;

        const allowed = (topic: GuideTopic) => {
            if (topic.superAdminOnly && !isSuperAdmin) {
                return false;
            }

            if (topic.permission && !isSuperAdmin && !granted.includes(topic.permission)) {
                return false;
            }

            return featureOn(topic.feature);
        };

        const needle = query.trim().toLowerCase();

        const matches = (topic: GuideTopic) =>
            needle === '' ||
            topic.title.toLowerCase().includes(needle) ||
            topic.summary.toLowerCase().includes(needle) ||
            topic.keywords.some((keyword) => keyword.includes(needle));

        return GUIDE_CHAPTERS.map((chapter) => ({
            ...chapter,
            topics: chapter.topics.filter((topic) => allowed(topic) && matches(topic)),
        })).filter((chapter) => chapter.topics.length > 0);
    }, [features, granted, isSuperAdmin, query]);

    const total = chapters.reduce((sum, chapter) => sum + chapter.topics.length, 0);

    return (
        <>
            <Head title="Panduan" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Panduan"
                    description="Petunjuk pemakaian aplikasi, disesuaikan dengan hak akses dan fitur sekolah Anda."
                />

                <div className="relative max-w-md">
                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                    <Input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Cari topik, misal: impor, sholat, notifikasi"
                        className="pl-9"
                    />
                </div>

                {total === 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Tidak ada topik yang cocok</CardTitle>
                            <CardDescription>
                                Coba kata kunci lain, atau kosongkan pencarian untuk melihat semua topik.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    chapters.map((chapter) => (
                        <Card key={chapter.id}>
                            <CardHeader>
                                <div className="flex items-center gap-2">
                                    <BookOpen className="size-5 text-blue-600" />
                                    <CardTitle>{chapter.title}</CardTitle>
                                </div>
                                <CardDescription>{chapter.description}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Accordion type="single" collapsible className="w-full">
                                    {chapter.topics.map((topic) => (
                                        <AccordionItem key={topic.id} value={topic.id}>
                                            <AccordionTrigger>
                                                <span className="flex flex-col items-start gap-0.5 text-left">
                                                    <span>{topic.title}</span>
                                                    <span className="text-muted-foreground text-xs font-normal">
                                                        {topic.summary}
                                                    </span>
                                                </span>
                                            </AccordionTrigger>
                                            <AccordionContent>
                                                {CONTENT[topic.id] ?? null}
                                            </AccordionContent>
                                        </AccordionItem>
                                    ))}
                                </Accordion>
                            </CardContent>
                        </Card>
                    ))
                )}
            </div>
        </>
    );
}

PanduanIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Panduan', href: '/admin/panduan' },
    ],
};
