import { usePage } from '@inertiajs/react';
import { Github, Instagram, Linkedin, Twitter } from 'lucide-react';
import BrandLogo from '@/components/brand-logo';

const productLinks = [
    { label: 'Fitur', href: '#features' },
    { label: 'Harga', href: '#pricing' },
    { label: 'Demo', href: '#demo' },
    { label: 'Roadmap', href: '#roadmap' },
];

const resourceLinks = [
    { label: 'Dokumentasi', href: '#docs' },
    { label: 'Blog', href: '#blog' },
    { label: 'Bantuan', href: '#help' },
    { label: 'Status', href: '#status' },
];

const companyLinks = [
    { label: 'Tentang', href: '#about' },
    { label: 'Kontak', href: '#contact' },
    { label: 'Karir', href: '#careers' },
    { label: 'Privasi', href: '#privacy' },
];

const socials = [
    { icon: Twitter, label: 'Twitter', href: '#' },
    { icon: Instagram, label: 'Instagram', href: '#' },
    { icon: Linkedin, label: 'LinkedIn', href: '#' },
    { icon: Github, label: 'GitHub', href: '#' },
];

function FooterColumn({
    title,
    links,
}: {
    title: string;
    links: { label: string; href: string }[];
}) {
    return (
        <div>
            <h4 className="mb-4 text-sm font-semibold text-foreground">
                {title}
            </h4>
            <ul className="space-y-2.5 text-sm text-muted-foreground">
                {links.map((link) => (
                    <li key={link.label}>
                        <a
                            href={link.href}
                            className="transition hover:text-foreground"
                        >
                            {link.label}
                        </a>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export function Footer() {
    const { name } = usePage().props;

    return (
        <footer className="border-t bg-zinc-50 dark:bg-zinc-950">
            <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    {/* Brand */}
                    <div>
                        <div className="flex items-center gap-2 font-bold">
                            <BrandLogo className="size-8" />
                            <span>{name}</span>
                        </div>
                        <p className="mt-3 text-sm leading-relaxed text-muted-foreground">
                            Sistem absensi sekolah modern berbasis QR Code
                            dengan notifikasi WhatsApp real-time.
                        </p>
                        <div className="mt-5 flex items-center gap-3">
                            {socials.map((social) => (
                                <a
                                    key={social.label}
                                    href={social.href}
                                    className="text-muted-foreground transition hover:text-foreground"
                                    aria-label={social.label}
                                >
                                    <social.icon className="size-4.5" />
                                </a>
                            ))}
                        </div>
                    </div>

                    {/* Columns */}
                    <FooterColumn title="Produk" links={productLinks} />
                    <FooterColumn title="Sumber Daya" links={resourceLinks} />
                    <FooterColumn title="Perusahaan" links={companyLinks} />
                </div>

                <div className="mt-10 flex flex-col items-center justify-between gap-4 border-t pt-8 text-sm text-muted-foreground sm:flex-row">
                    <p>
                        {new Date().getFullYear()} {name} - Dibuat di Indonesia
                    </p>
                    <div className="flex gap-6">
                        <a
                            href="#privacy"
                            className="transition hover:text-foreground"
                        >
                            Privacy Policy
                        </a>
                        <a
                            href="#terms"
                            className="transition hover:text-foreground"
                        >
                            Terms of Service
                        </a>
                    </div>
                </div>
            </div>
        </footer>
    );
}
