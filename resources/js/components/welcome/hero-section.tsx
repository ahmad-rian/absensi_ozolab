import { useRef } from 'react';
import { Link } from '@inertiajs/react';
import { useGSAP } from '@gsap/react';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { ArrowRight, ChevronDown, PlayCircle, Sparkles } from 'lucide-react';
import { HeroVisual } from '@/components/welcome/hero-visual';
import { Button } from '@/components/ui/button';
import { useGsapScrollSync } from '@/hooks/use-gsap-scroll';

gsap.registerPlugin(useGSAP, ScrollTrigger);

const HEADLINE_LEAD = ['Satu', 'Platform,'];
const HEADLINE_REST = ['Untuk', 'Semua', 'Sekolah', 'Anda.'];

// Cute inline SVG avatars for the social-proof row (self-contained, no network).
const AVATARS: { bg: string; face: React.ReactNode }[] = [
    {
        bg: '#FDE68A',
        face: (
            <>
                <circle cx="13" cy="15" r="2" fill="#3f3f46" />
                <circle cx="23" cy="15" r="2" fill="#3f3f46" />
                <path d="M13 22c1.8 2.2 8.2 2.2 10 0" stroke="#3f3f46" strokeWidth="1.8" strokeLinecap="round" fill="none" />
                <circle cx="9" cy="19" r="1.6" fill="#fb7185" opacity="0.6" />
                <circle cx="27" cy="19" r="1.6" fill="#fb7185" opacity="0.6" />
            </>
        ),
    },
    {
        bg: '#BFDBFE',
        face: (
            <>
                <path d="M11 15h4M21 15h4" stroke="#3f3f46" strokeWidth="1.8" strokeLinecap="round" />
                <circle cx="18" cy="21" r="2.2" fill="#3f3f46" />
            </>
        ),
    },
    {
        bg: '#FBCFE8',
        face: (
            <>
                <path d="M11 14c1-1.5 3-1.5 4 0M21 14c1-1.5 3-1.5 4 0" stroke="#3f3f46" strokeWidth="1.8" strokeLinecap="round" fill="none" />
                <path d="M14 21c1.2 1.6 6.8 1.6 8 0" stroke="#3f3f46" strokeWidth="1.8" strokeLinecap="round" fill="none" />
            </>
        ),
    },
    {
        bg: '#BBF7D0',
        face: (
            <>
                <circle cx="13" cy="15" r="2" fill="#3f3f46" />
                <circle cx="23" cy="15" r="2" fill="#3f3f46" />
                <path d="M14 21h8" stroke="#3f3f46" strokeWidth="1.8" strokeLinecap="round" />
            </>
        ),
    },
    {
        bg: '#DDD6FE',
        face: (
            <>
                <path d="M12 16c0-2 3-2 3 0M21 16c0-2 3-2 3 0" stroke="#3f3f46" strokeWidth="1.8" strokeLinecap="round" fill="none" />
                <circle cx="18" cy="21" r="3" fill="none" stroke="#3f3f46" strokeWidth="1.8" />
            </>
        ),
    },
];

export function HeroSection() {
    const root = useRef<HTMLElement>(null);

    // Keep ScrollTrigger in lockstep with Lenis smooth scroll.
    useGsapScrollSync();

    useGSAP(
        () => {
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (reduceMotion) {
                gsap.set('[data-hero-reveal], [data-hero-word], [data-hero-visual]', { opacity: 1, y: 0, x: 0, scale: 1, filter: 'blur(0px)' });
                return;
            }

            // Entrance timeline: staggered reveal top-to-bottom, mockup pops in last.
            const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });

            tl.from('[data-hero-badge]', { y: 18, autoAlpha: 0, filter: 'blur(8px)', duration: 0.7 })
                .from(
                    '[data-hero-word]',
                    { yPercent: 120, autoAlpha: 0, filter: 'blur(6px)', duration: 0.9, stagger: 0.05, ease: 'expo.out' },
                    '-=0.35',
                )
                .from('[data-hero-desc]', { y: 20, autoAlpha: 0, filter: 'blur(6px)', duration: 0.7 }, '-=0.5')
                .from('[data-hero-proof]', { y: 18, autoAlpha: 0, filter: 'blur(4px)', duration: 0.6 }, '-=0.45')
                .from('[data-hero-cta]', { y: 22, autoAlpha: 0, filter: 'blur(4px)', duration: 0.6, stagger: 0.1 }, '-=0.4')
                .from('[data-hero-sub]', { y: 16, autoAlpha: 0, duration: 0.5 }, '-=0.4')
                .from('[data-hero-visual]', { y: 60, autoAlpha: 0, scale: 0.94, filter: 'blur(10px)', duration: 1, ease: 'power4.out' }, '-=0.35')
                .from('[data-hero-card]', { autoAlpha: 0, scale: 0.85, duration: 0.6, stagger: 0.12, ease: 'back.out(1.5)' }, '-=0.7');

            // Continuous float + scroll parallax on the floating cards. The float
            // loop starts after entrance so its `y` tween doesn't fight the reveal.
            // Parallax uses `yPercent` (a separate transform channel) so it composes
            // cleanly with the float loop.
            tl.add(() => {
                gsap.utils.toArray<HTMLElement>('[data-hero-card]').forEach((card) => {
                    const speed = Number(card.dataset.heroFloat ?? 1);
                    gsap.to(card, {
                        y: `-=${6 + speed * 2}`,
                        duration: 2.6 + speed * 0.35,
                        ease: 'sine.inOut',
                        repeat: -1,
                        yoyo: true,
                    });

                    const parallax = Number(card.dataset.parallax ?? -60);
                    gsap.to(card, {
                        yPercent: parallax * 0.14,
                        ease: 'none',
                        scrollTrigger: { trigger: root.current, start: 'top top', end: 'bottom top', scrub: 1 },
                    });
                });
            });

            // Gentle parallax drift of the whole copy block as the user scrolls out.
            gsap.to('[data-hero-copy]', {
                yPercent: -10,
                autoAlpha: 0.7,
                ease: 'none',
                scrollTrigger: { trigger: root.current, start: 'top top', end: 'bottom top', scrub: 1 },
            });

            // Subtle upward drift of the mockup for depth on scroll.
            gsap.to('[data-hero-mockup]', {
                yPercent: -6,
                ease: 'none',
                scrollTrigger: { trigger: root.current, start: 'top top', end: 'bottom top', scrub: 1 },
            });
        },
        { scope: root },
    );

    return (
        <section id="hero" ref={root} aria-labelledby="hero-heading" className="relative overflow-hidden">
            {/* ---------- Layered background ---------- */}
            {/* Aurora / mesh gradient glow — center glow keeps the headline from feeling empty */}
            <div aria-hidden="true" className="pointer-events-none absolute inset-0 overflow-hidden">
                <div
                    className="animate-aurora absolute -top-40 left-1/2 size-[52rem] -translate-x-1/2 rounded-full opacity-40 blur-[140px] dark:opacity-50"
                    style={{ background: 'radial-gradient(circle, var(--chart-1) 0%, transparent 65%)' }}
                />
                <div
                    className="animate-aurora-slow absolute top-1/4 -right-24 size-[40rem] rounded-full opacity-30 blur-[140px] dark:opacity-40"
                    style={{ background: 'radial-gradient(circle, var(--chart-2) 0%, transparent 65%)' }}
                />
                <div
                    className="animate-aurora absolute top-1/4 -left-24 size-[38rem] rounded-full opacity-25 blur-[150px] dark:opacity-35"
                    style={{ background: 'radial-gradient(circle, var(--chart-5) 0%, transparent 70%)' }}
                />
            </div>

            {/* Refined grid with radial mask */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 [mask-image:radial-gradient(ellipse_65%_50%_at_50%_30%,black,transparent)]"
            >
                <div
                    className="absolute inset-0 opacity-[0.05] dark:opacity-[0.09]"
                    style={{
                        backgroundImage:
                            'linear-gradient(to right, var(--foreground) 1px, transparent 1px), linear-gradient(to bottom, var(--foreground) 1px, transparent 1px)',
                        backgroundSize: '48px 48px',
                    }}
                />
            </div>

            {/* Top soft accent wash */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-x-0 top-0 h-[540px] bg-gradient-to-b from-[var(--accent)]/50 to-transparent dark:from-[var(--accent)]/20"
            />

            {/* ---------- Content ---------- */}
            <div className="relative z-10 mx-auto flex w-full max-w-6xl flex-col items-center px-4 pt-32 pb-24 text-center sm:px-6 lg:pt-36 lg:px-8">
                <div data-hero-copy className="flex flex-col items-center">
                    {/* Pill badge */}
                    <div data-hero-reveal data-hero-badge>
                        <span className="border-border/70 bg-card/60 text-foreground group relative inline-flex items-center gap-2 overflow-hidden rounded-full border px-4 py-1.5 text-xs font-medium shadow-sm backdrop-blur-md">
                            <span className="relative flex size-2">
                                <span className="bg-chart-2 absolute inline-flex size-full animate-ping rounded-full opacity-60" />
                                <span className="bg-chart-2 relative inline-flex size-2 rounded-full" />
                            </span>
                            <Sparkles className="text-primary size-3.5" />
                            Dipercaya 200+ Sekolah di Indonesia
                            <span className="animate-hero-shine pointer-events-none absolute inset-y-0 -left-1/2 w-1/3 skew-x-[-20deg] bg-gradient-to-r from-transparent via-white/40 to-transparent dark:via-white/10" />
                        </span>
                    </div>

                    {/* Headline */}
                    <h1
                        id="hero-heading"
                        data-hero-reveal
                        className="text-foreground mt-7 max-w-4xl text-5xl leading-[1.03] font-extrabold tracking-tight text-balance sm:text-6xl lg:text-[4.75rem]"
                    >
                        <span className="block overflow-hidden pb-1">
                            {HEADLINE_LEAD.map((word) => (
                                <span key={word} className="mr-[0.28em] inline-block">
                                    <span data-hero-word className="inline-block">
                                        {word}
                                    </span>
                                </span>
                            ))}
                        </span>
                        <span className="block overflow-hidden pb-2">
                            {HEADLINE_REST.map((word) => (
                                <span key={word} className="relative mr-[0.28em] inline-block">
                                    <span
                                        data-hero-word
                                        className="inline-block bg-gradient-to-br from-[var(--primary)] via-[var(--chart-1)] to-[var(--chart-2)] bg-clip-text text-transparent"
                                    >
                                        {word}
                                    </span>
                                </span>
                            ))}
                        </span>
                    </h1>

                    {/* Subtitle */}
                    <p data-hero-reveal data-hero-desc className="text-muted-foreground mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-pretty">
                        Daftarkan sekolah Anda dalam 5 menit. Kelola absensi, kirim notifikasi WhatsApp ke orang tua, dan pantau
                        kehadiran siswa dari satu dashboard.
                    </p>

                    {/* Social proof row */}
                    <div data-hero-reveal data-hero-proof className="mt-8 flex flex-wrap items-center justify-center gap-x-4 gap-y-3">
                        <div className="flex items-center">
                            <div className="flex -space-x-3">
                                {AVATARS.map((av, i) => (
                                    <svg
                                        key={i}
                                        viewBox="0 0 36 36"
                                        className="ring-background size-9 rounded-full shadow-sm ring-2"
                                        style={{ background: av.bg }}
                                    >
                                        {av.face}
                                    </svg>
                                ))}
                            </div>
                            <span className="border-border/70 bg-card/60 text-foreground ml-3 inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-semibold shadow-sm backdrop-blur-md">
                                200+ sekolah bergabung
                            </span>
                        </div>
                    </div>

                    {/* CTAs */}
                    <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <div data-hero-reveal data-hero-cta>
                            <Button
                                size="lg"
                                className="group relative overflow-hidden rounded-[1.3rem] px-6 text-sm font-semibold shadow-lg shadow-[var(--primary)]/25"
                                asChild
                            >
                                <Link href="/daftar">
                                    Daftarkan Siswa Gratis
                                    <ArrowRight className="ml-1 size-4 transition-transform group-hover:translate-x-0.5" />
                                    <span className="animate-hero-shine pointer-events-none absolute inset-y-0 -left-1/2 w-1/3 skew-x-[-20deg] bg-white/25" />
                                </Link>
                            </Button>
                        </div>
                        <div data-hero-reveal data-hero-cta>
                            <Button
                                variant="outline"
                                size="lg"
                                className="border-border/70 bg-card/50 rounded-[1.3rem] px-6 text-sm font-semibold backdrop-blur-md"
                                asChild
                            >
                                <a href="#how-it-works">
                                    <PlayCircle className="mr-1 size-4" />
                                    Lihat Demo
                                </a>
                            </Button>
                        </div>
                    </div>

                    {/* Small subtitle line */}
                    <p data-hero-reveal data-hero-sub className="text-muted-foreground mt-5 text-sm">
                        Gratis hingga 100 siswa &middot; Tanpa kartu kredit &middot; Setup 5 menit
                    </p>
                </div>

                {/* ---------- Product mockup ---------- */}
                <div className="mt-16 w-full lg:mt-20">
                    <HeroVisual />
                </div>
            </div>

            <div className="absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce">
                <a href="#features" aria-label="Gulir ke bawah" className="text-muted-foreground hover:text-foreground transition">
                    <ChevronDown className="size-6" />
                </a>
            </div>
        </section>
    );
}
