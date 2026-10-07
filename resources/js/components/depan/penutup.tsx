import { Link, usePage } from '@inertiajs/react';
import BrandLogo from '@/components/brand-logo';
import { Awan, KertasSobek } from '@/components/depan/kolase';
import { Muncul } from '@/components/depan/muncul';

const TAUTAN = [
    { label: 'Daftarkan Siswa', href: '/daftar' },
    { label: 'Masuk', href: '/login' },
    { label: 'Hubungkan Telegram', href: '/daftar-telegram' },
];

export function Penutup() {
    const { name } = usePage().props as { name: string };

    return (
        <>
            <section className="px-2 sm:px-3">
                <div className="langit relative isolate overflow-hidden rounded-[28px] px-5 pt-24 pb-44 text-center sm:pb-52">
                    <div className="halftone absolute inset-0 -z-10 opacity-50" />
                    <Awan className="top-10 -left-8 -z-10 h-20 w-56" />
                    <Awan className="top-28 -right-10 -z-10 h-24 w-64 max-sm:hidden" />
                    <Muncul
                        as="h2"
                        className="serif mx-auto max-w-2xl text-[clamp(2.1rem,4.6vw,3.6rem)] leading-[1.06]"
                    >
                        Sekolah anak Anda bekerja sama dengan Tyas Photo?{' '}
                        <em>Daftarkan sekarang.</em>
                    </Muncul>
                    <Muncul tunda={150} className="mt-8">
                        <Link
                            href="/daftar"
                            className="inline-block rounded-full bg-[var(--kartu)] px-6 py-3 font-semibold shadow-[0_8px_24px_-10px_rgb(29_27_25/0.5)] transition hover:-translate-y-0.5"
                        >
                            Daftarkan Siswa
                        </Link>
                    </Muncul>
                    <KertasSobek
                        benih={61}
                        kasar={20}
                        className="halftone bottom-0 left-0 -z-10 h-32 w-1/2 bg-[var(--ungu)]"
                    />
                    <KertasSobek
                        benih={67}
                        kasar={14}
                        className="bergaris right-0 bottom-0 -z-10 h-40 w-[40%]"
                    />
                    <KertasSobek
                        benih={71}
                        kasar={10}
                        className="bottom-0 left-[30%] -z-10 h-24 w-[45%] bg-[var(--dasar)]"
                    />
                </div>
            </section>

            <footer className="mx-auto flex max-w-6xl flex-col gap-6 px-5 py-10 md:flex-row md:items-center md:justify-between">
                <div className="flex items-center gap-3">
                    <BrandLogo className="size-8 rounded-md" />
                    <div>
                        <p className="font-semibold">{name}</p>
                        <p className="text-sm text-[var(--tinta-3)]">
                            Dikelola Ozolab · Purwokerto, Jawa Tengah
                        </p>
                    </div>
                </div>
                <nav
                    aria-label="Tautan kaki"
                    className="flex flex-wrap gap-x-6 gap-y-2 text-sm text-[var(--tinta-2)]"
                >
                    {TAUTAN.map((t) => (
                        <Link
                            key={t.href}
                            href={t.href}
                            className="hover:text-[var(--tinta)]"
                        >
                            {t.label}
                        </Link>
                    ))}
                </nav>
                <p className="angka text-xs text-[var(--tinta-3)]">
                    © {new Date().getFullYear()} Tyas Photo
                </p>
            </footer>
        </>
    );
}
