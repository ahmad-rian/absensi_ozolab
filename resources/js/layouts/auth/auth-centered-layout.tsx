import { Link, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Moon, Sun } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthCenteredLayout({ children, title, description }: AuthLayoutProps) {
    const { name } = usePage().props;
    const { resolvedAppearance, updateAppearance } = useAppearance();

    const toggleTheme = () => updateAppearance(resolvedAppearance === 'dark' ? 'light' : 'dark');

    return (
        <div className="relative flex min-h-dvh flex-col items-center justify-center overflow-hidden bg-zinc-50 px-4 py-10 sm:px-6 dark:bg-zinc-950">
            {/* Background: grid pattern */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,theme(colors.zinc.200)_1px,transparent_1px),linear-gradient(to_bottom,theme(colors.zinc.200)_1px,transparent_1px)] bg-[size:44px_44px] opacity-60 [mask-image:radial-gradient(ellipse_at_center,black_20%,transparent_75%)] dark:bg-[linear-gradient(to_right,theme(colors.zinc.800)_1px,transparent_1px),linear-gradient(to_bottom,theme(colors.zinc.800)_1px,transparent_1px)] dark:opacity-40"
            />

            {/* Background: gradient glows */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute -top-40 left-1/2 -z-0 h-[38rem] w-[38rem] -translate-x-1/2 rounded-full bg-gradient-to-br from-blue-400/25 via-indigo-400/20 to-transparent blur-3xl dark:from-blue-600/20 dark:via-indigo-600/15"
            />
            <div
                aria-hidden="true"
                className="pointer-events-none absolute -bottom-48 right-[-10%] -z-0 h-[34rem] w-[34rem] rounded-full bg-gradient-to-tl from-indigo-400/20 via-sky-400/15 to-transparent blur-3xl dark:from-indigo-600/15 dark:via-sky-600/10"
            />

            {/* Theme toggle */}
            <div className="absolute top-4 right-4 z-20">
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-9 rounded-xl bg-white/40 backdrop-blur-sm hover:bg-white/70 dark:bg-white/5 dark:hover:bg-white/10"
                    onClick={toggleTheme}
                >
                    <Sun className="size-4 rotate-0 scale-100 transition-transform dark:-rotate-90 dark:scale-0" />
                    <Moon className="absolute size-4 rotate-90 scale-0 transition-transform dark:rotate-0 dark:scale-100" />
                    <span className="sr-only">Ganti tema</span>
                </Button>
            </div>

            <motion.div
                className="relative z-10 w-full max-w-md"
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, ease: 'easeOut' }}
            >
                {/* Logo + app name */}
                <div className="mb-6 flex flex-col items-center gap-3 text-center">
                    <Link href={home()} className="group flex flex-col items-center gap-3">
                        <div className="flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-600 shadow-lg shadow-blue-500/30 transition-transform group-hover:scale-105">
                            <AppLogoIcon className="size-7 fill-current text-white" />
                        </div>
                        <span className="text-lg font-bold tracking-tight">{name as string}</span>
                    </Link>
                </div>

                {/* Card */}
                <div className="rounded-2xl border border-zinc-200/80 bg-white/80 p-6 shadow-xl shadow-zinc-950/5 backdrop-blur-xl sm:p-8 dark:border-white/10 dark:bg-zinc-900/70 dark:shadow-black/20">
                    <div className="mb-6 flex flex-col gap-1.5 text-center">
                        <h1 className="text-2xl font-extrabold tracking-tight">{title}</h1>
                        {description && <p className="text-muted-foreground text-sm text-balance">{description}</p>}
                    </div>

                    {children}
                </div>

                <p className="text-muted-foreground mt-6 text-center text-xs">
                    &copy; {new Date().getFullYear()} {name as string}
                </p>
            </motion.div>
        </div>
    );
}
