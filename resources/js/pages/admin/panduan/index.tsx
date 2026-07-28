import { Head, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { GuideNav } from '@/components/panduan/guide-nav';
import { GUIDE_CHAPTERS, type FlatTopic, type GuideTopic } from '@/components/panduan/guide-registry';
import { GuideToc } from '@/components/panduan/guide-toc';
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
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    const page = usePage();
    const { auth, features } = page.props as unknown as {
        auth: { user: { roles?: string[]; permissions?: string[] } | null };
        features?: Partial<SchoolFeatureMap>;
    };

    const isSuperAdmin = (auth?.user?.roles ?? []).includes('SUPER_ADMIN');
    const granted = auth?.user?.permissions ?? [];

    /**
     * Topik diratakan dan dinomori SETELAH disaring, jadi "Langkah 3 dari 12"
     * selalu sesuai dengan yang benar-benar terlihat pembacanya.
     */
    const topics = useMemo<FlatTopic[]>(() => {
        // Aturan penyaringan PERSIS sama dengan app-sidebar.tsx: super admin
        // melompati cek permission tapi TIDAK melompati cek fitur.
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

        let step = 0;

        return GUIDE_CHAPTERS.flatMap((chapter) =>
            chapter.topics.filter(allowed).map((topic) => {
                step += 1;

                return { ...topic, chapterTitle: chapter.title, step };
            }),
        );
    }, [features, granted, isSuperAdmin]);

    const [activeId, setActiveId] = useState(() => initialTopic(page.url, topics));
    const [query, setQuery] = useState('');

    const active = topics.find((topic) => topic.id === activeId) ?? topics[0];

    const select = useCallback((id: string) => {
        setActiveId(id);

        // Topik disimpan di query string supaya bisa dibagikan dan tidak hilang
        // saat halaman dimuat ulang. replaceState, bukan push: berpindah langkah
        // bukan navigasi, dan tombol Back harus keluar dari Panduan.
        const url = new URL(window.location.href);
        url.searchParams.set('topik', id);
        window.history.replaceState({}, '', url.toString());
    }, []);

    useEffect(() => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }, [activeId]);

    const needle = query.trim().toLowerCase();
    const visible =
        needle === ''
            ? topics
            : topics.filter(
                  (topic) =>
                      topic.title.toLowerCase().includes(needle) ||
                      topic.summary.toLowerCase().includes(needle) ||
                      topic.keywords.some((keyword) => keyword.includes(needle)),
              );

    return (
        <>
            <Head title="Panduan" />

            <div className="flex h-full flex-1 flex-col gap-8 p-4 md:p-6">
                <header className="max-w-3xl space-y-3">
                    <p className="text-primary text-xs font-semibold tracking-[0.2em] uppercase">
                        Pusat Bantuan
                    </p>
                    <h1 className="text-3xl font-bold tracking-tight md:text-4xl">
                        Panduan Absensi Ozolab
                    </h1>
                    <p className="text-muted-foreground leading-relaxed">
                        Pelajari setiap fitur dan alur kerja aplikasi secara berurutan — dari menyiapkan
                        sekolah, mencatat kehadiran harian, sampai membaca laporan. Isinya menyesuaikan hak
                        akses dan fitur yang aktif di sekolah Anda.
                    </p>
                </header>

                {topics.length === 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Belum ada topik untuk akun ini</CardTitle>
                        </CardHeader>
                        <CardContent className="text-muted-foreground text-sm">
                            Hubungi admin sekolah Anda untuk meminta hak akses.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-6 lg:grid-cols-[minmax(0,18rem)_minmax(0,1fr)]">
                        <div className="space-y-3">
                            <div className="relative">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                <Input
                                    value={query}
                                    onChange={(event) => setQuery(event.target.value)}
                                    placeholder="Cari topik…"
                                    className="pl-9"
                                />
                            </div>

                            {visible.length === 0 ? (
                                <Card>
                                    <CardContent className="text-muted-foreground p-4 text-sm">
                                        Tidak ada topik yang cocok.
                                    </CardContent>
                                </Card>
                            ) : (
                                <GuideToc topics={visible} activeId={active.id} onSelect={select} />
                            )}
                        </div>

                        <Card>
                            <CardHeader>
                                <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                    {active.chapterTitle}
                                </p>
                                <CardTitle className="text-xl">
                                    {active.step}. {active.title}
                                </CardTitle>
                                <p className="text-muted-foreground text-sm">{active.summary}</p>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                {CONTENT[active.id] ?? null}

                                <GuideNav
                                    step={active.step}
                                    total={topics.length}
                                    onPrev={() => {
                                        const prev = topics[active.step - 2];
                                        if (prev) {
                                            select(prev.id);
                                        }
                                    }}
                                    onNext={() => {
                                        const next = topics[active.step];
                                        if (next) {
                                            select(next.id);
                                        }
                                    }}
                                />
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </>
    );
}

function initialTopic(url: string, topics: FlatTopic[]): string {
    const requested = new URL(url, 'http://localhost').searchParams.get('topik');

    return topics.some((topic) => topic.id === requested) ? (requested as string) : (topics[0]?.id ?? '');
}

PanduanIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Panduan', href: '/admin/panduan' },
    ],
};
