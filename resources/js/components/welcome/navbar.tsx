import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Moon, Sun, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { dashboard } from '@/routes';

const navLinks = [
    { label: 'Fitur', href: '#features' },
    { label: 'Institusi', href: '#institutions' },
    { label: 'Cara Kerja', href: '#how-it-works' },
    // { label: 'Keamanan', href: '#multi-tenant' },
    { label: 'Dashboard', href: '#dashboard' },
    { label: 'WhatsApp', href: '#whatsapp' },
    { label: 'Testimonial', href: '#testimonials' },
    { label: 'FAQ', href: '#faq' },
];

export function Navbar() {
    const [scrolled, setScrolled] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);
    const [mobileVisible, setMobileVisible] = useState(false);
    const { auth, name } = usePage().props as { auth: { user: unknown }; name: string };
    const { resolvedAppearance, updateAppearance } = useAppearance();

    const toggleTheme = () => updateAppearance(resolvedAppearance === 'dark' ? 'light' : 'dark');

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 10);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    // Animate open/close with smooth transitions
    useEffect(() => {
        if (mobileOpen) {
            document.body.style.overflow = 'hidden';
            requestAnimationFrame(() => {
                requestAnimationFrame(() => setMobileVisible(true));
            });
        } else {
            setMobileVisible(false);
            const timer = setTimeout(() => {
                document.body.style.overflow = '';
            }, 300);
            return () => clearTimeout(timer);
        }
        return () => { document.body.style.overflow = ''; };
    }, [mobileOpen]);

    const scrollTo = useCallback((href: string) => {
        setMobileOpen(false);
        setTimeout(() => {
            document.querySelector(href)?.scrollIntoView({ behavior: 'smooth' });
        }, 350);
    }, []);

    return (
        <>
            <nav
                className={`fixed top-0 right-0 left-0 z-50 transition-all duration-300 ${scrolled
                        ? 'border-b border-border/60 bg-background/80 backdrop-blur-xl'
                        : 'bg-transparent'
                    }`}
            >
                <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                    <Link href="/" className="flex items-center gap-2.5 font-bold">
                        <div className="flex size-8 items-center justify-center rounded-lg bg-gradient-to-br from-blue-600 to-indigo-600">
                            <AppLogoIcon className="size-4.5 fill-current text-white" />
                        </div>
                        <span className="text-lg tracking-tight">{name}</span>
                    </Link>

                    {/* Desktop */}
                    <div className="hidden items-center gap-1 lg:flex">
                        {navLinks.map((link) => (
                            <a
                                key={link.href}
                                href={link.href}
                                className="text-muted-foreground hover:text-foreground rounded-lg px-3 py-2 text-sm font-medium transition"
                                onClick={(e) => { e.preventDefault(); scrollTo(link.href); }}
                            >
                                {link.label}
                            </a>
                        ))}
                        <div className="bg-border mx-3 h-5 w-px" />
                        <Button variant="ghost" size="icon" className="size-9" onClick={toggleTheme}>
                            <Sun className="size-4 rotate-0 scale-100 transition-transform dark:-rotate-90 dark:scale-0" />
                            <Moon className="absolute size-4 rotate-90 scale-0 transition-transform dark:rotate-0 dark:scale-100" />
                        </Button>
                        {auth.user ? (
                            <Button size="sm" className="ml-2" asChild>
                                <Link href={dashboard()}>Dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                <Button variant="ghost" size="sm" asChild>
                                    <Link href="/login">Masuk</Link>
                                </Button>
                                <Button size="sm" className="bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-md shadow-blue-500/25" asChild>
                                    <Link href="/register">Daftarkan Sekolah</Link>
                                </Button>
                            </>
                        )}
                    </div>

                    {/* Mobile toggle */}
                    <div className="flex items-center gap-1 lg:hidden">
                        <Button variant="ghost" size="icon" className="size-9" onClick={toggleTheme}>
                            <Sun className="size-4 rotate-0 scale-100 transition-transform dark:-rotate-90 dark:scale-0" />
                            <Moon className="absolute size-4 rotate-90 scale-0 transition-transform dark:rotate-0 dark:scale-100" />
                        </Button>
                        <button
                            onClick={() => setMobileOpen(true)}
                            className="flex size-9 items-center justify-center rounded-lg transition hover:bg-accent"
                            aria-label="Buka menu"
                        >
                            <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </nav>

            {/* Mobile fullscreen overlay with smooth animation */}
            {mobileOpen && (
                <div
                    className={`fixed inset-0 z-[60] flex flex-col transition-all duration-300 ease-out ${mobileVisible
                            ? 'bg-background/95 backdrop-blur-2xl'
                            : 'pointer-events-none bg-background/0 backdrop-blur-none'
                        }`}
                >
                    {/* Header */}
                    <div className={`flex h-16 shrink-0 items-center justify-between px-4 transition-all duration-300 ${mobileVisible ? 'translate-y-0 opacity-100' : '-translate-y-4 opacity-0'
                        }`}>
                        <Link href="/" className="flex items-center gap-2.5 font-bold" onClick={() => setMobileOpen(false)}>
                            <div className="flex size-8 items-center justify-center rounded-lg bg-gradient-to-br from-blue-600 to-indigo-600">
                                <AppLogoIcon className="size-4.5 fill-current text-white" />
                            </div>
                            <span className="text-lg tracking-tight">{name}</span>
                        </Link>
                        <button
                            onClick={() => setMobileOpen(false)}
                            className="flex size-10 items-center justify-center rounded-full bg-muted/80 transition-colors hover:bg-muted"
                            aria-label="Tutup menu"
                        >
                            <X className="size-5" />
                        </button>
                    </div>

                    {/* Links with stagger animation */}
                    <div className="flex flex-1 flex-col gap-0.5 overflow-y-auto px-3 pt-4">
                        {navLinks.map((link, i) => (
                            <a
                                key={link.href}
                                href={link.href}
                                className={`text-foreground hover:bg-accent/80 active:bg-accent rounded-2xl px-5 py-4 text-[17px] font-medium transition-all duration-300 ease-out ${mobileVisible
                                        ? 'translate-x-0 opacity-100'
                                        : 'translate-x-8 opacity-0'
                                    }`}
                                style={{ transitionDelay: mobileVisible ? `${60 + i * 40}ms` : '0ms' }}
                                onClick={(e) => { e.preventDefault(); scrollTo(link.href); }}
                            >
                                {link.label}
                            </a>
                        ))}
                    </div>

                    {/* Bottom CTA */}
                    <div
                        className={`shrink-0 border-t border-border/50 p-4 pb-8 transition-all duration-300 ease-out ${mobileVisible ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0'
                            }`}
                        style={{ transitionDelay: mobileVisible ? '380ms' : '0ms' }}
                    >
                        {auth.user ? (
                            <Button className="h-12 w-full rounded-xl text-base" asChild>
                                <Link href={dashboard()}>Dashboard</Link>
                            </Button>
                        ) : (
                            <div className="flex flex-col gap-3">
                                <Button className="h-12 w-full rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-base text-white shadow-lg shadow-blue-500/25" asChild>
                                    <Link href="/register">
                                        Daftarkan Sekolah
                                        <ArrowRight className="ml-2 size-4" />
                                    </Link>
                                </Button>
                                <Button variant="outline" className="h-12 w-full rounded-xl text-base" asChild>
                                    <Link href="/login">Masuk ke Akun</Link>
                                </Button>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </>
    );
}
