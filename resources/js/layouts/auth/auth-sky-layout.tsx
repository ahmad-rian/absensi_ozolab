import { Link, usePage } from '@inertiajs/react';
import { LogIn, Moon, Sun } from 'lucide-react';
import BrandLogo from '@/components/brand-logo';
import { useAppearance } from '@/hooks/use-appearance';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/** Gumpalan awan di bawah layar: tiap item satu lingkaran blur. */
const AWAN = [
    { left: '-8%', bottom: '-14%', size: '42rem' },
    { left: '22%', bottom: '-22%', size: '38rem' },
    { left: '55%', bottom: '-18%', size: '40rem' },
    { left: '80%', bottom: '-12%', size: '34rem' },
    { left: '8%', bottom: '4%', size: '22rem' },
    { left: '68%', bottom: '6%', size: '24rem' },
];

/** Lengkung tipis di belakang kartu, seperti riak dari tengah layar. */
const LENGKUNG = [380, 560, 760];

function LatarLangit() {
    return (
        <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-0"
        >
            <div className="absolute inset-0 bg-[linear-gradient(180deg,#8EC2EA_0%,#B9DAF3_38%,#E4F0FA_72%,#F4F8FC_100%)] dark:bg-[linear-gradient(180deg,#07111F_0%,#0C1D33_45%,#132A45_100%)]" />

            <svg
                className="absolute top-1/2 left-1/2 h-[1600px] w-[1600px] -translate-x-1/2 -translate-y-1/2"
                viewBox="0 0 1600 1600"
                fill="none"
            >
                {LENGKUNG.map((r) => (
                    <circle
                        key={r}
                        cx="800"
                        cy="800"
                        r={r}
                        className="stroke-white/45 dark:stroke-white/[0.06]"
                        strokeWidth="1"
                    />
                ))}
            </svg>

            {AWAN.map((a) => (
                <div
                    key={`${a.left}-${a.bottom}`}
                    className="absolute rounded-full bg-white/80 blur-3xl dark:bg-sky-200/[0.06]"
                    style={{
                        left: a.left,
                        bottom: a.bottom,
                        width: a.size,
                        height: `calc(${a.size} * 0.55)`,
                    }}
                />
            ))}
        </div>
    );
}

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
                <div className="w-full max-w-[400px] rounded-3xl border border-white/80 bg-[linear-gradient(180deg,rgba(232,244,253,0.92)_0%,rgba(255,255,255,0.96)_42%)] p-7 shadow-[0_24px_60px_-20px_rgba(30,64,110,0.35)] backdrop-blur-xl sm:p-9 dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(24,40,62,0.92)_0%,rgba(15,23,36,0.96)_42%)] dark:shadow-black/40">
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
