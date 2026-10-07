import { Link } from '@inertiajs/react';
import { AnimasiLottie } from '@/components/depan/animasi-lottie';
import { Awan, KertasSobek } from '@/components/depan/kolase';
import { Muncul } from '@/components/depan/muncul';

/** Contoh kehadiran per kelas di jendela aplikasi. Angka contoh, bukan data nyata. */
const KELAS = [
    {
        nama: 'VII A',
        hadir: 31,
        total: 32,
        warna: 'var(--koral)',
        telat: ['Eka W. · 07.16'],
    },
    { nama: 'VII B', hadir: 30, total: 30, warna: 'var(--tinta)', telat: [] },
    {
        nama: 'VIII A',
        hadir: 28,
        total: 31,
        warna: 'var(--mentega)',
        telat: ['Bima S. · 07.21', 'Dewi K. · 07.24'],
    },
    { nama: 'IX C', hadir: 32, total: 32, warna: 'var(--mint)', telat: [] },
] as const;

const MENU = ['Beranda', 'Siswa', 'Absensi', 'Kartu & foto', 'Laporan'];

function JendelaAplikasi() {
    return (
        <div className="overflow-hidden rounded-t-2xl border border-b-0 border-[var(--garis)] bg-[var(--kartu)] shadow-[0_-20px_60px_-30px_rgb(29_27_25/0.45)]">
            <div className="flex items-center gap-1.5 border-b border-[var(--garis)] px-4 py-2.5">
                {['#ef6a5a', '#f4bf4f', '#61c454'].map((c) => (
                    <span
                        key={c}
                        className="size-2.5 rounded-full"
                        style={{ background: c }}
                    />
                ))}
                <span className="mx-auto rounded-md bg-[var(--dasar)] px-3 py-0.5 text-[0.7rem] text-[var(--tinta-3)]">
                    tyasphoto.ozolab.id
                </span>
            </div>
            <div className="flex">
                <aside className="hidden w-40 shrink-0 border-r border-[var(--garis)] p-3 text-[0.78rem] sm:block">
                    {MENU.map((m) => (
                        <p
                            key={m}
                            className={`rounded-md px-2 py-1.5 ${m === 'Absensi' ? 'bg-[var(--dasar)] font-semibold' : 'text-[var(--tinta-2)]'}`}
                        >
                            {m}
                        </p>
                    ))}
                </aside>
                <div className="min-w-0 flex-1 p-4">
                    <div className="flex items-baseline justify-between">
                        <p className="text-sm font-semibold">
                            Kehadiran hari ini
                        </p>
                        <p className="angka text-[0.72rem] text-[var(--tinta-3)]">
                            Senin, 6 Okt
                        </p>
                    </div>
                    <div className="mt-3 grid grid-cols-2 gap-2.5 md:grid-cols-4">
                        {KELAS.map((k) => (
                            <div
                                key={k.nama}
                                className="rounded-xl border-2 bg-[var(--kartu)] p-2.5 text-[0.72rem]"
                                style={{ borderColor: k.warna }}
                            >
                                <p className="font-semibold">Kelas {k.nama}</p>
                                <p className="angka mt-1 text-[var(--tinta-2)]">
                                    {k.hadir}/{k.total} hadir
                                </p>
                                <div className="mt-2 space-y-1">
                                    {k.telat.length === 0 ? (
                                        <p className="rounded bg-[var(--hijau-muda)] px-1.5 py-0.5 text-[var(--hijau)]">
                                            Lengkap
                                        </p>
                                    ) : (
                                        k.telat.map((t) => (
                                            <p
                                                key={t}
                                                className="angka truncate rounded bg-[var(--kuning-muda)] px-1.5 py-0.5 text-[var(--kuning)]"
                                            >
                                                {t}
                                            </p>
                                        ))
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}

/** Kartu pelajar yang "ditempel" miring di atas kolase. Warna tetap: kartu fisik tidak ikut mode gelap. */
function KartuMiring() {
    return (
        <div className="w-44 rotate-[-7deg] rounded-xl bg-white p-2.5 text-[#1d1b19] shadow-[0_18px_40px_-18px_rgb(29_27_25/0.55)]">
            <div className="rounded-md bg-[#2f7fd0] px-2 py-1 text-[0.6rem] font-semibold tracking-wider text-white">
                KARTU PELAJAR
            </div>
            <div className="mt-2 flex gap-2">
                <div className="halftone aspect-[3/4] w-12 rounded bg-[#aab5f2]" />
                <div className="text-[0.62rem] leading-snug">
                    <p className="font-semibold">Alya Rahmawati</p>
                    <p className="text-[#8c867f]">VIII B</p>
                </div>
            </div>
            <div className="mt-1 flex justify-center">
                <AnimasiLottie nama="scan" className="size-20" />
            </div>
        </div>
    );
}

export function Hero() {
    return (
        <section className="px-2 pt-2 sm:px-3 sm:pt-3">
            <div className="langit relative isolate overflow-hidden rounded-[28px] pt-32 sm:pt-36">
                <div className="halftone absolute inset-0 -z-10 [mask-image:linear-gradient(to_bottom,black,transparent_70%)] opacity-60" />
                <Awan className="top-24 -left-10 -z-10 h-24 w-64 opacity-90 max-sm:top-20 max-sm:h-16 max-sm:w-40" />
                <Awan className="top-14 -right-12 -z-10 h-28 w-72 max-sm:hidden" />
                <p
                    aria-hidden="true"
                    className="serif pointer-events-none absolute inset-x-0 top-[46%] -z-10 text-center text-[26vw] leading-none text-[var(--isi-tyas)] select-none [-webkit-text-stroke:2.5px_var(--garis-tyas)] [text-shadow:0_10px_40px_rgb(47_127_208/0.18)]"
                >
                    Tyas
                </p>

                <div className="mx-auto max-w-3xl px-5 text-center">
                    <Muncul
                        as="h1"
                        className="serif text-[clamp(2.6rem,6.4vw,5rem)] leading-[1.02]"
                    >
                        Foto sekolah, kartu pelajar, dan absensi{' '}
                        <em>dalam satu alur</em>
                    </Muncul>
                    <Muncul
                        as="p"
                        tunda={150}
                        className="mx-auto mt-5 max-w-xl text-[1.05rem] leading-relaxed text-[var(--tinta-2)]"
                    >
                        Orang tua mendaftar online, siswa difoto di studio,
                        kartu ber-QR dipindai di gerbang, dan orang tua langsung
                        tahu saat anaknya terlambat.
                    </Muncul>
                    <Muncul
                        tunda={280}
                        className="mt-8 flex items-center justify-center gap-5"
                    >
                        <Link
                            href="/daftar"
                            className="rounded-full bg-[var(--kartu)] px-6 py-3 font-semibold shadow-[0_8px_24px_-10px_rgb(29_27_25/0.5)] transition hover:-translate-y-0.5"
                        >
                            Daftarkan Siswa
                        </Link>
                        <Link
                            href="/login"
                            className="font-semibold underline decoration-1 underline-offset-4"
                        >
                            Masuk
                        </Link>
                    </Muncul>
                </div>

                <div className="relative mt-16 h-[21rem] sm:h-[25rem]">
                    <KertasSobek
                        benih={3}
                        kasar={18}
                        className="halftone bottom-0 left-0 h-40 w-[45%] bg-[var(--ungu)]"
                    />
                    <KertasSobek
                        benih={11}
                        kasar={10}
                        className="bottom-0 left-[8%] h-56 w-[55%] bg-[var(--putih)]"
                    />
                    <KertasSobek
                        benih={7}
                        kasar={22}
                        className="right-0 bottom-0 h-64 w-[30%] bg-[var(--arang)]"
                    />
                    <KertasSobek
                        benih={19}
                        kasar={8}
                        className="bergaris right-0 bottom-0 h-48 w-[26%]"
                    />

                    <Muncul
                        gaya="cetak"
                        tunda={350}
                        miring="0deg"
                        className="absolute inset-x-3 bottom-0 mx-auto max-w-4xl sm:inset-x-10"
                    >
                        <JendelaAplikasi />
                    </Muncul>
                    <Muncul
                        gaya="cetak"
                        tunda={650}
                        miring="-14deg"
                        className="absolute bottom-10 left-[3%] hidden xl:block"
                    >
                        <KartuMiring />
                    </Muncul>
                </div>
            </div>
        </section>
    );
}
