import { Link } from '@inertiajs/react';
import { ArrowRight, ChevronDown, PlayCircle } from 'lucide-react';
import { BgBlobs } from '@/components/welcome/bg-blobs';
import { BgGrid } from '@/components/welcome/bg-grid';
import { HeroVisual } from '@/components/welcome/hero-visual';
import { Button } from '@/components/ui/button';

export function HeroSection() {
    return (
        <section id="hero" aria-labelledby="hero-heading" className="relative flex min-h-dvh items-center overflow-hidden">
            <BgGrid />
            <BgBlobs />

            <div className="relative z-10 mx-auto flex w-full max-w-7xl items-center gap-12 px-4 py-24 sm:px-6 lg:px-8">
                <div className="fade-up-stagger flex-1">
                    <span className="bg-accent text-foreground border-border inline-flex items-center rounded-full border px-3.5 py-1.5 text-xs font-medium">
                        Dipercaya 200+ Sekolah di Indonesia
                    </span>

                    <h1
                        id="hero-heading"
                        className="text-foreground mt-6 text-5xl leading-[1.08] font-extrabold tracking-tight sm:text-6xl lg:text-7xl"
                    >
                        <span className="bg-gradient-to-r from-[var(--primary)] to-[var(--chart-1)] bg-clip-text text-transparent">
                            Satu Platform
                        </span>
                        , Untuk Semua Sekolah Anda.
                    </h1>

                    <p className="text-muted-foreground mt-6 max-w-xl text-lg leading-relaxed">
                        Daftarkan sekolah Anda dalam 5 menit. Kelola absensi, kirim notifikasi WhatsApp ke orang tua, dan pantau kehadiran siswa dari satu dashboard.
                    </p>

                    <div className="mt-8 flex flex-wrap items-center gap-3">
                        <Button size="lg" className="rounded-[1.3rem] px-6 text-sm font-semibold" asChild>
                            <Link href="/register">
                                Daftarkan Sekolah Gratis
                                <ArrowRight className="ml-1 size-4" />
                            </Link>
                        </Button>
                        <Button variant="outline" size="lg" className="rounded-[1.3rem] px-6 text-sm font-semibold" asChild>
                            <a href="#how-it-works">
                                <PlayCircle className="mr-1 size-4" />
                                Lihat Demo
                            </a>
                        </Button>
                    </div>

                    <p className="text-muted-foreground mt-5 text-sm">
                        Gratis hingga 100 siswa &middot; Tanpa kartu kredit &middot; Setup 5 menit
                    </p>
                </div>

                <HeroVisual />
            </div>

            <div className="absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce">
                <a href="#features" aria-label="Gulir ke bawah" className="text-muted-foreground hover:text-foreground transition">
                    <ChevronDown className="size-6" />
                </a>
            </div>
        </section>
    );
}
