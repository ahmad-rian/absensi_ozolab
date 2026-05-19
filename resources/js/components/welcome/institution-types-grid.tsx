import { Baby, BookOpen, Building2, GraduationCap, Home, Lightbulb, Moon, School } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';

interface InstitutionType {
    icon: ReactNode;
    title: string;
    description: string;
    badge?: string;
}

const institutions: InstitutionType[] = [
    {
        icon: <Baby className="size-5 text-[var(--primary)]" />,
        title: 'TK & PAUD',
        description: 'Absensi ramah anak dengan notifikasi orang tua instan',
    },
    {
        icon: <School className="size-5 text-[var(--primary)]" />,
        title: 'Sekolah Dasar',
        description: 'Pantau kehadiran ribuan siswa SD dengan mudah',
        badge: 'Populer',
    },
    {
        icon: <GraduationCap className="size-5 text-[var(--primary)]" />,
        title: 'SMP & SMA/SMK',
        description: 'Kelola multi-kelas dengan jadwal kompleks',
        badge: 'Populer',
    },
    {
        icon: <BookOpen className="size-5 text-[var(--primary)]" />,
        title: 'Madrasah',
        description: 'Mendukung jadwal sholat & kegiatan keagamaan',
    },
    {
        icon: <Moon className="size-5 text-[var(--primary)]" />,
        title: 'Pesantren',
        description: 'Absensi mukim dengan monitoring 24/7',
    },
    {
        icon: <Building2 className="size-5 text-[var(--primary)]" />,
        title: 'Universitas',
        description: 'Skalabel untuk puluhan ribu mahasiswa',
    },
    {
        icon: <Lightbulb className="size-5 text-[var(--primary)]" />,
        title: 'Bimbel & Kursus',
        description: 'Track kehadiran per sesi dengan presisi',
    },
    {
        icon: <Home className="size-5 text-[var(--primary)]" />,
        title: 'Homeschooling',
        description: 'Komunitas terdistribusi tetap terhubung',
    },
];

function InstitutionCard({ institution }: { institution: InstitutionType }) {
    return (
        <div className="bg-card relative rounded-2xl border border-border p-5 transition-all duration-200 hover:scale-[1.02] hover:border-[var(--primary)]">
            {institution.badge && (
                <Badge className="absolute top-4 right-4">{institution.badge}</Badge>
            )}
            <div className="bg-accent mb-3 flex size-10 items-center justify-center rounded-xl">
                {institution.icon}
            </div>
            <h3 className="text-foreground text-sm font-bold">{institution.title}</h3>
            <p className="text-muted-foreground mt-1 text-sm leading-relaxed">{institution.description}</p>
        </div>
    );
}

export function InstitutionTypesGrid() {
    return (
        <section id="institutions" className="relative overflow-hidden bg-gradient-to-b from-blue-50/80 to-indigo-50/60 py-24 sm:py-32 lg:py-40 dark:from-blue-950/30 dark:to-indigo-950/20">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="mx-auto mb-14 max-w-2xl text-center animate-fade-up">
                    <h2 className="text-foreground text-3xl font-bold tracking-tight sm:text-4xl">
                        Dirancang untuk Semua Jenis Institusi Pendidikan
                    </h2>
                    <p className="text-muted-foreground mt-4 text-lg">
                        Dari TK hingga universitas, AbsenKu menyesuaikan kebutuhan unik setiap institusi.
                    </p>
                </div>

                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    {institutions.map((institution) => (
                        <InstitutionCard key={institution.title} institution={institution} />
                    ))}
                </div>
            </div>
        </section>
    );
}
