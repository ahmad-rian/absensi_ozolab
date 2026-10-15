import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import BrandLogo from '@/components/brand-logo';
import { KELAS_KARTU_KACA, LatarLangit } from '@/components/shared/latar-langit';
import { ThemeToggle } from '@/components/shared/theme-toggle';

/**
 * Kulit halaman pendaftaran publik.
 *
 * Dipakai bersama oleh `/daftar` (form panjang berlangkah) dan `/quick-regis`
 * (empat isian, satu halaman). Disatukan di sini supaya keduanya tidak bisa
 * berbeda diam-diam — keduanya tautan yang dibagikan ke sekolah, dan yang satu
 * terlihat seperti halaman internal sistem adalah kesan yang salah.
 *
 * Halaman yang memakainya WAJIB dikecualikan dari AppLayout di `app.tsx`. Tanpa
 * itu ia dirender di dalam sidebar admin, lengkap dengan menu yang tidak boleh
 * dilihat pendaftar.
 */
export function RegistrationShell({ title, children }: { title: string; children: ReactNode }) {
    const { name, app } = usePage<{ name?: string; app?: { workspace_name?: string | null } | null }>().props;

    return (
        <>
            <Head title={title} />
            <div className="relative isolate flex min-h-dvh flex-col">
                <LatarLangit tetap />
                <header className="relative z-10 flex items-center justify-between px-5 py-5 sm:px-8">
                    <a href="/" className="flex items-center gap-2 rounded-md focus-visible:ring-2 focus-visible:ring-zinc-900/30 focus-visible:outline-none">
                        <BrandLogo className="size-7 fill-current text-zinc-900 dark:text-white" />
                        <span className="text-sm font-semibold text-zinc-900 dark:text-white">{app?.workspace_name ?? name}</span>
                    </a>
                    <ThemeToggle />
                </header>
                <div className="relative z-10 flex flex-1 flex-col">{children}</div>
            </div>
        </>
    );
}

/**
 * Logo, judul, subjudul. Logo sekolah dipakai begitu sekolahnya dipilih; sebelum
 * itu lambang aplikasi yang tampil.
 */
export function RegistrationHeader({
    logoPath,
    schoolName,
    title,
    subtitle,
}: {
    logoPath?: string | null;
    schoolName?: string;
    title: string;
    subtitle: string;
}) {
    // Sebelum sekolah dipilih: logo aplikasi dari branding publik, baru
    // lambang bawaan kalau branding itu pun belum diunggah.
    const { app } = usePage<{ app?: { logo?: string | null } | null }>().props;
    const src = logoPath ? `/storage/${logoPath}` : (app?.logo ?? null);

    return (
        <div className="mb-6 text-center">
            <div className="mx-auto mb-4 flex size-16 items-center justify-center rounded-2xl border border-white bg-white shadow-[0_6px_16px_-6px_rgba(30,64,110,0.35)] dark:border-white/10 dark:bg-white/10">
                {src ? (
                    <img src={src} alt={schoolName ?? title} className="size-11 rounded-lg object-contain" />
                ) : (
                    <AppLogoIcon className="size-8 fill-current text-zinc-800 dark:text-zinc-100" />
                )}
            </div>
            <h1 className="text-2xl font-semibold tracking-tight text-balance text-zinc-900 sm:text-3xl dark:text-white">{title}</h1>
            <p className="mx-auto mt-2 max-w-md text-sm text-balance text-zinc-600 sm:text-base dark:text-zinc-400">{subtitle}</p>
        </div>
    );
}

/** Kartu putih bernomor. `/quick-regis` cuma punya satu, `/daftar` punya enam. */
export function RegistrationSection({ number, title, children }: { number: number; title: string; children: ReactNode }) {
    return (
        <div className={`p-5 sm:p-7 ${KELAS_KARTU_KACA}`}>
            <div className="mb-5 flex items-center gap-3">
                <span className="flex size-8 shrink-0 items-center justify-center rounded-xl bg-zinc-900 text-sm font-semibold text-white dark:bg-white dark:text-zinc-900">
                    {number}
                </span>
                <h2 className="text-lg font-semibold">{title}</h2>
            </div>
            {children}
        </div>
    );
}

export function RegistrationFooter() {
    return (
        <footer className="mt-auto py-6 text-center">
            <p className="text-xs text-zinc-500">
                Powered by <span className="font-semibold">Tyas Photo</span>
            </p>
        </footer>
    );
}
