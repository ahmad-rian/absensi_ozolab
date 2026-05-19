import { Link, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Moon, Sun } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSplitLayout({ children, title, description }: AuthLayoutProps) {
    const { name } = usePage().props;
    const { resolvedAppearance, updateAppearance } = useAppearance();

    const toggleTheme = () => updateAppearance(resolvedAppearance === 'dark' ? 'light' : 'dark');

    return (
        <div className="relative grid min-h-dvh lg:grid-cols-2">
            {/* Left - School Image */}
            <div className="relative hidden overflow-hidden lg:block">
                <img
                    src="/images/school-auth.webp"
                    alt="Suasana sekolah"
                    className="absolute inset-0 size-full object-cover"
                />
                {/* Dark overlay gradient */}
                <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-black/20" />

                {/* Content on image */}
                <div className="relative z-10 flex h-full flex-col justify-between p-10">
                    <Link href={home()} className="flex items-center gap-2.5">
                        <div className="flex size-9 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm">
                            <AppLogoIcon className="size-5 fill-current text-white" />
                        </div>
                        <span className="text-lg font-bold text-white">{name as string}</span>
                    </Link>

                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.6, delay: 0.2 }}
                    >
                        <h2 className="text-3xl leading-tight font-extrabold text-white">
                            Sistem Absensi
                            <br />
                            Berbasis QR Code
                        </h2>
                        <p className="mt-3 max-w-md text-sm leading-relaxed text-white/70">
                            Pantau kehadiran siswa secara real-time dengan pemindaian QR Code dan terima notifikasi langsung via WhatsApp.
                        </p>
                        <div className="mt-4 text-xs text-white/40">
                            &copy; {new Date().getFullYear()} Absensi OZOLAB
                        </div>
                    </motion.div>
                </div>
            </div>

            {/* Right - Form */}
            <div className="relative flex flex-col">
                {/* Theme toggle */}
                <div className="absolute top-4 right-4 z-10">
                    <Button variant="ghost" size="icon" className="size-9 rounded-xl" onClick={toggleTheme}>
                        <Sun className="size-4 rotate-0 scale-100 transition-transform dark:-rotate-90 dark:scale-0" />
                        <Moon className="absolute size-4 rotate-90 scale-0 transition-transform dark:rotate-0 dark:scale-100" />
                    </Button>
                </div>

                <div className="flex flex-1 items-center justify-center overflow-y-auto p-6 sm:p-8 lg:p-12">
                    <motion.div
                        className="mx-auto flex w-full max-w-lg flex-col justify-center space-y-8"
                        initial={{ opacity: 0, y: 16 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.5 }}
                    >
                        {/* Mobile logo */}
                        <Link href={home()} className="flex items-center justify-center gap-2 lg:hidden">
                            <div className="flex size-10 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600">
                                <AppLogoIcon className="size-5 fill-current text-white" />
                            </div>
                            <span className="text-lg font-bold">{name as string}</span>
                        </Link>

                        <div className="flex flex-col gap-2">
                            <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">{title}</h1>
                            <p className="text-muted-foreground text-balance">{description}</p>
                        </div>

                        {children}
                    </motion.div>
                </div>
            </div>
        </div>
    );
}
