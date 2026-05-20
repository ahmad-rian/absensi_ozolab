import { Link } from '@inertiajs/react';

function DecorativeCircle({ className }: { className: string }) {
    return (
        <svg
            className={`${className} animate-spin-slow`}
            viewBox="0 0 120 120"
            fill="none"
            aria-hidden="true"
        >
            <circle cx="60" cy="60" r="50" stroke="currentColor" strokeWidth="1" strokeDasharray="8 6" />
            <circle cx="60" cy="60" r="30" stroke="currentColor" strokeWidth="0.5" strokeDasharray="4 8" />
        </svg>
    );
}

export function CtaBanner() {
    return (
        <section className="relative overflow-hidden bg-gradient-to-b from-indigo-50/70 to-blue-50/50 py-24 sm:py-32 lg:py-40 dark:from-indigo-950/30 dark:to-blue-950/20">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    className="relative overflow-hidden rounded-2xl bg-zinc-900 px-6 py-16 text-center sm:px-12 sm:py-20 animate-fade-up dark:bg-zinc-800"
                >
                    {/* Decorative circles */}
                    <DecorativeCircle className="absolute -top-8 -left-8 size-32 text-zinc-700 opacity-40" />
                    <DecorativeCircle className="absolute -right-10 -bottom-10 size-40 text-zinc-700 opacity-30" />

                    <div className="relative z-10">
                        <h2 className="text-2xl font-bold tracking-tight text-white sm:text-3xl lg:text-4xl">
                            Siap Memodernisasi Absensi Sekolah Anda?
                        </h2>
                        <p className="mx-auto mt-4 max-w-xl text-base text-zinc-300 sm:text-lg">
                            Mulai gratis hari ini. Setup hanya 5 menit.
                        </p>
                        <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row sm:gap-4">
                            <Link
                                href="/daftar"
                                className="inline-flex h-11 items-center justify-center rounded-lg bg-white px-6 text-sm font-semibold text-zinc-900 shadow-lg transition hover:bg-zinc-100"
                            >
                                Daftar Sekarang
                            </Link>
                            <a
                                href="#contact"
                                className="inline-flex h-11 items-center justify-center rounded-lg border border-zinc-600 bg-zinc-700 px-6 text-sm font-semibold text-zinc-200 transition hover:bg-zinc-600"
                            >
                                Hubungi Sales
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
