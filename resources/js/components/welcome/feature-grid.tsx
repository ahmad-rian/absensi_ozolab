import { FileSpreadsheet, LayoutDashboard, MessageCircle, QrCode, Shield, Wifi } from 'lucide-react';
import { FeatureCard } from '@/components/welcome/feature-card';

const features = [
    {
        icon: <QrCode className="size-5 text-[var(--primary)]" />,
        title: 'Scan QR Instan',
        description: 'Pemindaian kurang dari 1 detik. Siswa cukup tempelkan kartu, kehadiran langsung tercatat.',
    },
    {
        icon: <MessageCircle className="size-5 text-[var(--primary)]" />,
        title: 'Notifikasi WhatsApp Otomatis',
        description: 'Orang tua langsung tahu saat anak hadir atau tidak hadir di sekolah, tanpa perlu cek manual.',
    },
    {
        icon: <LayoutDashboard className="size-5 text-[var(--primary)]" />,
        title: 'Dashboard Real-time',
        description: 'Pantau kehadiran seluruh sekolah dari satu layar. Data diperbarui secara langsung.',
    },
    {
        icon: <Shield className="size-5 text-[var(--primary)]" />,
        title: 'Multi-Role Aman',
        description: 'Admin, Guru, dan Orang Tua masing-masing memiliki akses yang berbeda sesuai kebutuhan.',
    },
    {
        icon: <FileSpreadsheet className="size-5 text-[var(--primary)]" />,
        title: 'Laporan Otomatis',
        description: 'Rekap kehadiran bulanan siap unduh dengan sekali klik. Tidak perlu input manual lagi.',
    },
    {
        icon: <Wifi className="size-5 text-[var(--primary)]" />,
        title: 'Mode Offline-Ready',
        description: 'Tetap mencatat kehadiran saat internet putus. Data otomatis disinkronkan saat koneksi kembali.',
    },
];

export function FeatureGrid() {
    return (
        <section id="features" className="bg-zinc-50 py-24 sm:py-32 lg:py-40 dark:bg-zinc-900/50">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="mx-auto mb-14 max-w-2xl text-center animate-fade-up">
                    <h2 className="text-foreground text-3xl font-bold tracking-tight sm:text-4xl">
                        Semua yang Anda Butuhkan
                    </h2>
                    <p className="text-muted-foreground mt-4 text-lg">
                        Fitur lengkap untuk mengelola kehadiran siswa secara modern, cepat, dan aman.
                    </p>
                </div>

                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {features.map((feature) => (
                        <FeatureCard
                            key={feature.title}
                            icon={feature.icon}
                            title={feature.title}
                            description={feature.description}
                        />
                    ))}
                </div>
            </div>
        </section>
    );
}
