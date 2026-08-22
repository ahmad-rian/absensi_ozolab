import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';

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
    return (
        <>
            <Head title={title} />
            <div className="relative flex min-h-screen flex-col bg-zinc-50 dark:bg-zinc-950">
                {/* Grid pattern + gradient glow background */}
                <div aria-hidden="true" className="pointer-events-none absolute inset-0 overflow-hidden">
                    <div
                        className="absolute inset-0 opacity-[0.05] dark:opacity-[0.08] [mask-image:radial-gradient(ellipse_70%_55%_at_50%_0%,black,transparent)]"
                        style={{
                            backgroundImage:
                                'linear-gradient(to right, var(--foreground) 1px, transparent 1px), linear-gradient(to bottom, var(--foreground) 1px, transparent 1px)',
                            backgroundSize: '40px 40px',
                        }}
                    />
                    <div className="absolute -top-32 left-1/2 size-[42rem] -translate-x-1/2 rounded-full bg-blue-500/10 blur-[120px] dark:bg-blue-500/15" />
                </div>
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
    return (
        <div className="mb-6 text-center">
            {logoPath ? (
                <img src={`/storage/${logoPath}`} alt={schoolName ?? title} className="mx-auto mb-4 size-16 rounded-xl object-contain" />
            ) : (
                <div className="mx-auto mb-4 flex size-16 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600">
                    <AppLogoIcon className="size-8 fill-current text-white" />
                </div>
            )}
            <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">{title}</h1>
            <p className="text-muted-foreground mt-2 text-sm sm:text-base">{subtitle}</p>
        </div>
    );
}

/** Kartu putih bernomor. `/quick-regis` cuma punya satu, `/daftar` punya enam. */
export function RegistrationSection({ number, title, children }: { number: number; title: string; children: ReactNode }) {
    return (
        <div className="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <div className="mb-5 flex items-center gap-3">
                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-blue-600 to-indigo-600 text-sm font-bold text-white">
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
        <footer className="mt-auto border-t border-zinc-200 py-6 text-center dark:border-zinc-800">
            <p className="text-muted-foreground text-sm">
                Powered by <span className="font-semibold">Tyas Photo</span>
            </p>
        </footer>
    );
}
