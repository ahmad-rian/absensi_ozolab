import { Link, usePage } from '@inertiajs/react';
import { LogIn, Moon, Sun } from 'lucide-react';
import BrandLogo from '@/components/brand-logo';
import {
    KELAS_KARTU_KACA,
    LatarLangit,
} from '@/components/shared/latar-langit';
import { useAppearance } from '@/hooks/use-appearance';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSkyLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const page = usePage();
    const name = (page.props.name as string) ?? '';
    const passwordRequired = page.component === 'auth/ganti-password';
    const { resolvedAppearance, updateAppearance } = useAppearance();

    const merek = (
        <>
            <BrandLogo className="size-7 fill-current text-zinc-900 dark:text-white" />
            <span className="text-sm font-semibold text-zinc-900 dark:text-white">
                {name}
            </span>
        </>
    );

    return (
        <div className="relative isolate flex min-h-dvh flex-col overflow-hidden">
            <LatarLangit />

            <header className="relative z-10 flex items-center justify-between px-5 py-5 sm:px-8">
                {passwordRequired ? (
                    <span className="flex items-center gap-2">{merek}</span>
                ) : (
                    <Link
                        href={home()}
                        className="flex items-center gap-2 rounded-md focus-visible:ring-2 focus-visible:ring-zinc-900/30 focus-visible:outline-none"
                    >
                        {merek}
                    </Link>
                )}
                <button
                    type="button"
                    onClick={() =>
                        updateAppearance(
                            resolvedAppearance === 'dark' ? 'light' : 'dark',
                        )
                    }
                    className="relative flex size-9 items-center justify-center rounded-full bg-white/50 text-zinc-700 backdrop-blur transition hover:bg-white/80 focus-visible:ring-2 focus-visible:ring-zinc-900/30 focus-visible:outline-none dark:bg-white/10 dark:text-zinc-200 dark:hover:bg-white/15"
                >
                    <Sun className="size-4 scale-100 transition-transform dark:scale-0" />
                    <Moon className="absolute size-4 scale-0 transition-transform dark:scale-100" />
                    <span className="sr-only">Ganti tema</span>
                </button>
            </header>

            <main className="relative z-10 flex flex-1 items-center justify-center px-4 pb-16">
                <div
                    className={`w-full max-w-[400px] p-7 sm:p-9 ${KELAS_KARTU_KACA}`}
                >
                    <div className="mx-auto mb-5 flex size-12 items-center justify-center rounded-2xl border border-white bg-white shadow-[0_6px_16px_-6px_rgba(30,64,110,0.35)] dark:border-white/10 dark:bg-white/10">
                        <LogIn className="size-5 text-zinc-800 dark:text-zinc-100" />
                    </div>

                    <div className="mb-7 text-center">
                        <h1 className="text-xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                            {title}
                        </h1>
                        {description && (
                            <p className="mx-auto mt-2 max-w-[19rem] text-sm leading-relaxed text-balance text-zinc-500 dark:text-zinc-400">
                                {description}
                            </p>
                        )}
                    </div>

                    {children}
                </div>
            </main>

            <p className="relative z-10 pb-5 text-center text-xs text-zinc-500 dark:text-zinc-500">
                &copy; {new Date().getFullYear()} {name}
            </p>
        </div>
    );
}
