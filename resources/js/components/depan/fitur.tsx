import type { ReactNode } from 'react';
import { AnimasiLottie } from '@/components/depan/animasi-lottie';
import { KertasSobek } from '@/components/depan/kolase';
import { Muncul } from '@/components/depan/muncul';

const LOG = [
    ['06.41', 'Alya R.', 'VIII B', 'Hadir'],
    ['06.44', 'Bima S.', 'VII A', 'Hadir'],
    ['06.52', 'Citra N.', 'IX C', 'Hadir'],
    ['07.16', 'Eka W.', 'VII B', 'Terlambat'],
    ['06.58', 'Dimas P.', 'VIII A', 'Hadir'],
] as const;

function Jendela({ children }: { children: ReactNode }) {
    return (
        <div className="w-full max-w-md overflow-hidden rounded-2xl bg-[var(--kartu)] shadow-[0_24px_50px_-26px_rgb(29_27_25/0.6)]">
            {children}
        </div>
    );
}

function LogGerbang() {
    return (
        <Jendela>
            <div className="flex items-center justify-between border-b border-[var(--garis)] px-4 py-3">
                <p className="text-sm font-semibold">Gerbang utama</p>
                <AnimasiLottie nama="scan" className="size-10" />
            </div>
            <div className="h-44 overflow-hidden">
                <ul className="gulir-log">
                    {[...LOG, ...LOG].map(([jam, nama, kelas, status], i) => (
                        <li
                            key={i}
                            className="flex items-center gap-3 border-b border-[var(--garis)] px-4 py-2.5 text-sm"
                        >
                            <span className="angka text-[var(--tinta-3)]">
                                {jam}
                            </span>
                            <span className="min-w-0 flex-1 truncate">
                                {nama}{' '}
                                <span className="text-[var(--tinta-3)]">
                                    · {kelas}
                                </span>
                            </span>
                            <span
                                className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                                    status === 'Hadir'
                                        ? 'bg-[var(--hijau-muda)] text-[var(--hijau)]'
                                        : 'bg-[var(--kuning-muda)] text-[var(--kuning)]'
                                }`}
                            >
                                {status}
                            </span>
                        </li>
                    ))}
                </ul>
            </div>
        </Jendela>
    );
}

function PesanOrangTua() {
    return (
        <Jendela>
            <div className="flex items-center gap-3 border-b border-[var(--garis)] px-4 py-3">
                <span className="grid size-8 place-items-center rounded-full bg-[var(--mint)] text-xs font-bold text-[#1d1b19]">
                    TP
                </span>
                <p className="text-sm font-semibold">Tyas Photo</p>
                <AnimasiLottie nama="pesan" className="ml-auto size-10" />
            </div>
            <div className="space-y-3 bg-[var(--dasar)] p-4">
                <p className="max-w-[85%] rounded-2xl rounded-tl-sm bg-[var(--kartu)] p-3 text-sm leading-relaxed">
                    Ananda <b>Eka Wulandari</b> (VII B) tercatat{' '}
                    <b>terlambat</b> masuk sekolah hari ini pukul{' '}
                    <span className="angka">07.16</span>.
                    <span className="angka mt-1 block text-right text-[0.7rem] text-[var(--tinta-3)]">
                        07.16
                    </span>
                </p>
                <p className="ml-auto max-w-[60%] rounded-2xl rounded-tr-sm bg-[var(--hijau-muda)] p-3 text-sm">
                    Baik, terima kasih.
                </p>
            </div>
        </Jendela>
    );
}

function RekapKelas() {
    return (
        <Jendela>
            <div className="flex items-center justify-between border-b border-[var(--garis)] px-4 py-3">
                <p className="text-sm font-semibold">Laporan · Oktober</p>
                <span className="rounded-full border border-[var(--garis)] px-2.5 py-0.5 text-xs">
                    Excel per kelas
                </span>
            </div>
            <table className="angka w-full text-left text-sm">
                <thead className="text-xs text-[var(--tinta-3)]">
                    <tr>
                        {['Kelas', 'Hadir', 'Terlambat', 'Alpa'].map((h) => (
                            <th key={h} className="px-4 py-2 font-medium">
                                {h}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {[
                        ['VII A', '96%', 4, 1],
                        ['VII B', '98%', 2, 0],
                        ['VIII A', '93%', 7, 2],
                        ['IX C', '99%', 1, 0],
                    ].map(([k, h, t, a]) => (
                        <tr key={k} className="border-t border-[var(--garis)]">
                            <td className="px-4 py-2">{k}</td>
                            <td className="px-4 py-2 text-[var(--hijau)]">
                                {h}
                            </td>
                            <td className="px-4 py-2 text-[var(--kuning)]">
                                {t}
                            </td>
                            <td className="px-4 py-2">{a}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </Jendela>
    );
}

type Panel = {
    label: string;
    judul: ReactNode;
    isi: string;
    poin: string[];
    latar: ReactNode;
    visual: ReactNode;
};

const PANEL: Panel[] = [
    {
        label: 'Gerbang',
        judul: (
            <>
                Kartu ditempel, <em>hadir tercatat</em>
            </>
        ),
        isi: 'Pemindai cukup dibuka di browser: kamera HP atau tablet, barcode reader USB, atau perangkat STB di gerbang.',
        poin: [
            'Jam masuk dan pulang mengikuti jadwal sekolah',
            'Scan dua kali tidak tercatat ganda',
        ],
        latar: (
            <>
                <div className="halftone absolute inset-0 bg-[var(--ungu)]" />
                <KertasSobek
                    benih={31}
                    kasar={10}
                    className="bottom-0 left-0 h-1/3 w-full bg-[var(--putih)]"
                />
            </>
        ),
        visual: <LogGerbang />,
    },
    {
        label: 'Orang tua',
        judul: (
            <>
                Kabar datang <em>saat perlu saja</em>
            </>
        ),
        isi: 'WhatsApp hanya dikirim saat anak terlambat atau tidak hadir, jadi pesannya selalu dibaca.',
        poin: [
            'Riwayat lengkap di Portal Orang Tua',
            'Bisa juga lewat Telegram',
            'Satu akun untuk kakak dan adik',
        ],
        latar: (
            <>
                <div className="absolute inset-0 bg-[var(--mint)]" />
                <KertasSobek
                    benih={41}
                    kasar={14}
                    className="bergaris right-0 bottom-0 h-1/2 w-3/5"
                />
            </>
        ),
        visual: <PesanOrangTua />,
    },
    {
        label: 'Laporan',
        judul: (
            <>
                Rekap siap dibagikan, <em>satu sheet per kelas</em>
            </>
        ),
        isi: 'Hadir, terlambat, izin, sakit, dan alpa terhitung otomatis dari catatan gerbang. Unduh Excel atau PDF.',
        poin: [
            'Absensi sholat Dhuha dan Dzuhur bisa diaktifkan',
            'Pilih rentang tanggal dan kelas',
        ],
        latar: (
            <>
                <div className="halftone absolute inset-0 bg-[var(--koral)]" />
                <KertasSobek
                    benih={53}
                    kasar={18}
                    className="bottom-0 left-0 h-2/5 w-full bg-[var(--arang)]"
                />
            </>
        ),
        visual: <RekapKelas />,
    },
];

export function Fitur() {
    return (
        <section
            id="fitur"
            className="scroll-mt-24 space-y-6 px-2 pb-24 sm:px-3 sm:pb-32"
        >
            {PANEL.map((p, i) => (
                <div
                    key={p.label}
                    className="mx-auto grid max-w-6xl overflow-hidden rounded-[28px] bg-[var(--kartu)] md:grid-cols-2"
                >
                    <div
                        className={`relative isolate flex min-h-80 items-center justify-center p-6 sm:p-10 ${i % 2 === 1 ? 'md:order-2' : ''}`}
                    >
                        <div className="absolute inset-0 -z-10">{p.latar}</div>
                        <Muncul
                            gaya="cetak"
                            miring={i % 2 === 1 ? '3deg' : '-3deg'}
                            className="w-full"
                        >
                            <div className="flex justify-center">
                                {p.visual}
                            </div>
                        </Muncul>
                    </div>
                    <div className="flex flex-col justify-center p-8 sm:p-12">
                        <Muncul as="p" className="label text-[var(--tinta-3)]">
                            {p.label}
                        </Muncul>
                        <Muncul
                            as="h3"
                            tunda={100}
                            className="serif mt-3 text-[clamp(1.9rem,3.4vw,2.7rem)] leading-[1.08]"
                        >
                            {p.judul}
                        </Muncul>
                        <Muncul
                            as="p"
                            tunda={180}
                            className="mt-4 leading-relaxed text-[var(--tinta-2)]"
                        >
                            {p.isi}
                        </Muncul>
                        <ul className="mt-5 space-y-2">
                            {p.poin.map((poin, j) => (
                                <Muncul
                                    as="li"
                                    key={poin}
                                    tunda={260 + j * 80}
                                    className="flex items-center gap-2.5 text-sm"
                                >
                                    <span
                                        className="size-1.5 shrink-0 rounded-full bg-[var(--tinta)]"
                                        aria-hidden="true"
                                    />
                                    {poin}
                                </Muncul>
                            ))}
                        </ul>
                    </div>
                </div>
            ))}
        </section>
    );
}
