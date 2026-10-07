import { BookOpen, Database, IdCard, ScanLine, Sunrise } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { CSSProperties } from 'react';
import { AnimasiLottie } from '@/components/depan/animasi-lottie';
import { KertasSobek } from '@/components/depan/kolase';
import { Muncul } from '@/components/depan/muncul';

type Guna = {
    ikon: LucideIcon;
    judul: string;
    isi: string;
    warna: string;
    /** Letak catatan di sekeliling kartu, hanya di layar lebar. */
    letak: string;
    miring: string;
};

const GUNA: Guna[] = [
    {
        ikon: Database,
        judul: 'Database siswa',
        isi: 'Nama, NIS, kelas, foto, dan kontak orang tua tersimpan di satu tempat.',
        warna: 'bg-white',
        letak: 'lg:top-2 lg:left-0',
        miring: '-3deg',
    },
    {
        ikon: IdCard,
        judul: 'Kartu OSIS',
        isi: 'Kartu pelajar resmi dengan foto studio, siap dicetak.',
        warna: 'bg-[var(--mint)]',
        letak: 'lg:top-0 lg:right-0',
        miring: '3deg',
    },
    {
        ikon: ScanLine,
        judul: 'Kartu absensi',
        isi: 'QR pribadi dipindai di gerbang saat masuk dan pulang.',
        warna: 'bg-[var(--ungu)]',
        letak: 'lg:top-[44%] lg:-left-6',
        miring: '2deg',
    },
    {
        ikon: BookOpen,
        judul: 'Kartu perpustakaan',
        isi: 'Kartu yang sama dipakai untuk pinjam buku di perpustakaan.',
        warna: 'bergaris',
        letak: 'lg:top-[46%] lg:-right-6',
        miring: '-2deg',
    },
    {
        ikon: Sunrise,
        judul: 'Kartu sholat',
        isi: 'Scan kehadiran sholat Dhuha dan Dzuhur berjamaah.',
        warna: 'bg-[var(--koral)]',
        letak: 'lg:bottom-0 lg:left-1/2 lg:-translate-x-1/2',
        miring: '-1deg',
    },
];

/** Kartu pelajar besar di tengah. Warna tetap: kartu fisik tidak ikut mode gelap. */
function KartuBesar() {
    return (
        <div className="w-72 rounded-2xl bg-white p-4 text-[#1d1b19] shadow-[0_30px_60px_-28px_rgb(29_27_25/0.7)] sm:w-80 lg:rotate-[-4deg]">
            <div className="flex items-center justify-between rounded-lg bg-[#2f7fd0] px-3 py-1.5 text-[0.65rem] font-semibold tracking-wider text-white">
                <span>KARTU PELAJAR</span>
                <span className="opacity-80">OSIS · ABSENSI · PERPUS</span>
            </div>
            <div className="mt-4 flex gap-4">
                <div className="halftone aspect-[3/4] w-24 shrink-0 rounded-md bg-[#aab5f2]" />
                <div className="space-y-1.5 text-sm">
                    <p className="serif text-2xl leading-none">
                        Alya Rahmawati
                    </p>
                    <p className="angka text-xs text-[#57524c]">NIS 0072318</p>
                    <p className="text-xs text-[#57524c]">Kelas VIII B</p>
                </div>
            </div>
            <div className="mt-3 flex items-center justify-between">
                <p className="max-w-[9rem] text-[0.7rem] leading-snug text-[#8c867f]">
                    Satu QR untuk gerbang, sholat, dan perpustakaan.
                </p>
                <AnimasiLottie nama="scan" className="size-24" />
            </div>
        </div>
    );
}

export function SatuKartu() {
    return (
        <section className="px-2 pb-24 sm:px-3 sm:pb-32">
            <div className="relative isolate mx-auto max-w-6xl overflow-hidden rounded-[28px] px-5 py-20 sm:py-24">
                <div className="halftone absolute inset-0 -z-20 bg-[var(--langit-bawah)]" />
                <KertasSobek
                    benih={113}
                    kasar={14}
                    className="bottom-0 left-0 -z-10 h-1/3 w-full bg-[var(--dasar)]"
                />

                <div className="text-center">
                    <Muncul as="p" className="label text-[var(--tinta-2)]">
                        All in one card
                    </Muncul>
                    <Muncul
                        as="h2"
                        tunda={100}
                        className="serif mx-auto mt-3 max-w-2xl text-[clamp(2.1rem,4.6vw,3.6rem)] leading-[1.05]"
                    >
                        Satu kartu untuk <em>semua keperluan</em> siswa
                    </Muncul>
                </div>

                <div className="relative mx-auto mt-14 max-w-5xl lg:h-[34rem]">
                    <Muncul
                        gaya="cetak"
                        miring="-10deg"
                        className="flex justify-center lg:absolute lg:top-1/2 lg:left-1/2 lg:-translate-x-1/2 lg:-translate-y-1/2"
                    >
                        <KartuBesar />
                    </Muncul>

                    <ul className="mt-12 grid gap-4 sm:grid-cols-2 lg:mt-0 lg:block">
                        {GUNA.map((g, i) => (
                            <Muncul
                                as="li"
                                key={g.judul}
                                gaya="cetak"
                                tunda={300 + i * 120}
                                miring={g.miring}
                                className={`lg:absolute ${g.letak}`}
                            >
                                <div
                                    className={`${g.warna} w-full rounded-xl p-4 text-[#1d1b19] shadow-[0_16px_34px_-20px_rgb(29_27_25/0.6)] lg:w-64 lg:[rotate:var(--r)]`}
                                    style={{ '--r': g.miring } as CSSProperties}
                                >
                                    <div className="flex items-center gap-2.5">
                                        <g.ikon
                                            className="size-5 shrink-0"
                                            strokeWidth={1.6}
                                            aria-hidden="true"
                                        />
                                        <p className="font-semibold">
                                            {g.judul}
                                        </p>
                                    </div>
                                    <p className="mt-1.5 text-sm leading-relaxed text-[#57524c]">
                                        {g.isi}
                                    </p>
                                </div>
                            </Muncul>
                        ))}
                    </ul>
                </div>
            </div>
        </section>
    );
}
