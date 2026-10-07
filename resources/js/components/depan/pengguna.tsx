import type { ReactNode } from 'react';
import { AnimasiLottie } from '@/components/depan/animasi-lottie';
import { KertasSobek } from '@/components/depan/kolase';
import { Muncul } from '@/components/depan/muncul';

type Peran = {
    peran: string;
    kegunaan: string;
    latar: ReactNode;
    isi: ReactNode;
};

function Lembar({
    children,
    className = '',
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={`rounded-lg bg-white p-3 text-left text-[0.7rem] text-[#1d1b19] shadow-[0_14px_30px_-16px_rgb(29_27_25/0.6)] ${className}`}
        >
            {children}
        </div>
    );
}

const PERAN: Peran[] = [
    {
        peran: 'Petugas gerbang',
        kegunaan: 'Scan kartu tanpa daftar hadir kertas',
        latar: (
            <>
                <div className="halftone absolute inset-0 bg-[var(--ungu)]" />
                <KertasSobek
                    benih={21}
                    kasar={14}
                    className="bottom-0 left-0 h-1/2 w-full bg-[var(--putih)]"
                />
            </>
        ),
        isi: <AnimasiLottie nama="scan" className="size-36" />,
    },
    {
        peran: 'Orang tua',
        kegunaan: 'Kabar saat anak terlambat atau tidak hadir',
        latar: (
            <>
                <div className="absolute inset-0 bg-[var(--mint)]" />
                <KertasSobek
                    benih={5}
                    kasar={16}
                    className="bergaris right-0 bottom-0 h-3/5 w-2/3"
                />
            </>
        ),
        isi: <AnimasiLottie nama="pesan" className="size-36" />,
    },
    {
        peran: 'Wali kelas',
        kegunaan: 'Rekap per kelas, siap dibagikan',
        latar: (
            <>
                <div className="bergaris absolute inset-0" />
                <KertasSobek
                    benih={9}
                    kasar={20}
                    className="bottom-0 left-0 h-2/5 w-full bg-[var(--arang)]"
                />
            </>
        ),
        isi: (
            <Lembar className="w-36 -rotate-3">
                <p className="font-semibold">Kelas VIII A</p>
                {[
                    ['Hadir', '28'],
                    ['Terlambat', '2'],
                    ['Izin', '1'],
                ].map(([k, v]) => (
                    <p
                        key={k}
                        className="angka mt-1 flex justify-between border-t border-[#e9e5df] pt-1"
                    >
                        <span>{k}</span>
                        <span>{v}</span>
                    </p>
                ))}
            </Lembar>
        ),
    },
    {
        peran: 'Kepala sekolah',
        kegunaan: 'Seluruh sekolah dalam satu layar',
        latar: (
            <>
                <div className="halftone absolute inset-0 bg-[var(--koral)]" />
                <KertasSobek
                    benih={13}
                    kasar={12}
                    className="bottom-0 left-0 h-1/2 w-3/4 bg-[var(--mentega)]"
                />
            </>
        ),
        isi: <AnimasiLottie nama="laporan" className="size-36" />,
    },
    {
        peran: 'Pembimbing haji',
        kegunaan: 'Kartu dan absensi peserta manasik',
        latar: (
            <>
                <div className="langit absolute inset-0" />
                <KertasSobek
                    benih={17}
                    kasar={18}
                    className="halftone bottom-0 left-0 h-1/2 w-full bg-[var(--ungu)]"
                />
            </>
        ),
        isi: (
            <Lembar className="w-32 rotate-3">
                <div className="rounded bg-[#237a4b] px-1.5 py-0.5 text-[0.55rem] font-semibold tracking-wider text-white">
                    KARTU PESERTA
                </div>
                <div className="halftone mt-2 aspect-square w-full rounded bg-[#a3d9b9]" />
            </Lembar>
        ),
    },
];

export function Pengguna() {
    return (
        <section className="pb-24 sm:pb-32">
            <Muncul
                as="h2"
                className="serif px-5 text-center text-[clamp(2rem,4.2vw,3.1rem)] leading-[1.1]"
            >
                Dipakai setiap hari oleh
            </Muncul>
            <ul className="mx-auto mt-12 flex max-w-6xl snap-x snap-mandatory scroll-px-5 gap-5 overflow-x-auto px-5 pb-4 lg:grid lg:grid-cols-5 lg:overflow-visible">
                {PERAN.map((p, i) => (
                    <Muncul
                        as="li"
                        key={p.peran}
                        tunda={i * 100}
                        className="w-[62vw] max-w-60 shrink-0 snap-start text-center lg:w-auto lg:max-w-none"
                    >
                        <div className="relative isolate flex aspect-[4/5] items-center justify-center overflow-hidden rounded-2xl shadow-[0_18px_40px_-24px_rgb(29_27_25/0.6)]">
                            <div className="absolute inset-0 -z-10">
                                {p.latar}
                            </div>
                            {p.isi}
                        </div>
                        <p className="label mt-5">{p.peran}</p>
                        <p className="serif mt-1.5 text-[1.15rem] leading-snug text-[var(--tinta-2)] italic">
                            {p.kegunaan}
                        </p>
                    </Muncul>
                ))}
            </ul>
        </section>
    );
}
