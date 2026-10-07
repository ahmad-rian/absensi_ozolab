import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import BrandLogo from '@/components/brand-logo';
import { Muncul } from '@/components/depan/muncul';

const TAUTAN = [
    { label: 'Daftarkan Siswa', href: '/daftar' },
    { label: 'Masuk', href: '/login' },
    { label: 'Hubungkan Telegram', href: '/daftar-telegram' },
    { label: 'Tanya jawab', href: '#faq' },
];

export function Penutup() {
    const { name } = usePage().props as { name: string };

    return (
        <>
            <section className="mx-auto max-w-6xl px-4 pb-20 sm:px-6">
                <Muncul gaya="cetak">
                    <div className="tanda-potong flex flex-col items-start gap-8 rounded-[14px] bg-[var(--tinta)] p-8 text-[var(--kertas)] sm:p-12 md:flex-row md:items-end md:justify-between">
                        <div>
                            <p className="kode-tepi !text-[var(--kertas)] opacity-60">
                                Untuk orang tua
                            </p>
                            <h2 className="tampil mt-4 max-w-xl text-[clamp(1.7rem,3.2vw,2.6rem)] leading-tight font-extrabold">
                                Sekolah anak Anda sudah bekerja sama dengan Tyas
                                Photo? Daftarkan sekarang.
                            </h2>
                        </div>
                        <Link
                            href="/daftar"
                            className="group inline-flex h-12 shrink-0 items-center gap-2 rounded-lg bg-[var(--biru)] px-6 font-semibold text-white transition hover:brightness-110"
                        >
                            Daftarkan Siswa
                            <ArrowRight className="size-4 transition group-hover:translate-x-0.5" />
                        </Link>
                    </div>
                </Muncul>
            </section>

            <footer className="border-t border-[var(--garis)]">
                <div className="mx-auto flex max-w-6xl flex-col gap-8 px-4 py-10 sm:px-6 md:flex-row md:items-center md:justify-between">
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
                        {TAUTAN.map((t) =>
                            t.href.startsWith('#') ? (
                                <a
                                    key={t.href}
                                    href={t.href}
                                    className="hover:text-[var(--tinta)]"
                                >
                                    {t.label}
                                </a>
                            ) : (
                                <Link
                                    key={t.href}
                                    href={t.href}
                                    className="hover:text-[var(--tinta)]"
                                >
                                    {t.label}
                                </Link>
                            ),
                        )}
                    </nav>
                    <p className="data text-xs text-[var(--tinta-3)]">
                        © {new Date().getFullYear()}
                    </p>
                </div>
            </footer>
        </>
    );
}
