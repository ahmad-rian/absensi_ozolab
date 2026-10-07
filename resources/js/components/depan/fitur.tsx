import type { ReactNode } from 'react';
import { AnimasiLottie } from '@/components/depan/animasi-lottie';
import { Muncul } from '@/components/depan/muncul';

type Bagian = {
    kode: string;
    judul: string;
    pengantar: string;
    poin: string[];
    visual: ReactNode;
};

function PesanWhatsapp() {
    return (
        <div className="w-full max-w-sm rounded-[14px] border border-[var(--garis)] bg-[var(--kartu)] p-4">
            <AnimasiLottie nama="pesan" className="mx-auto size-28" />
            <div className="mt-2 rounded-lg rounded-tl-none bg-[var(--hijau-muda)] p-3 text-sm leading-relaxed">
                Ananda <b>Eka Wulandari</b> (VII B) tercatat <b>terlambat</b>{' '}
                masuk sekolah hari ini pukul <span className="data">07.16</span>
                .
                <span className="data mt-2 block text-right text-[0.68rem] text-[var(--tinta-3)]">
                    07.16 ✓✓
                </span>
            </div>
        </div>
    );
}

function RekapKelas() {
    const baris = [
        ['VII A', 31, 1, 0],
        ['VII B', 29, 2, 1],
        ['VIII A', 32, 0, 0],
    ] as const;

    return (
        <div className="w-full max-w-sm rounded-[14px] border border-[var(--garis)] bg-[var(--kartu)] p-4">
            <AnimasiLottie nama="laporan" className="mx-auto size-28" />
            <table className="data mt-2 w-full text-left text-xs">
                <thead className="text-[var(--tinta-3)]">
                    <tr>
                        <th className="py-1.5 font-medium">KELAS</th>
                        <th className="py-1.5 font-medium">HADIR</th>
                        <th className="py-1.5 font-medium">TERLAMBAT</th>
                        <th className="py-1.5 font-medium">ALPA</th>
                    </tr>
                </thead>
                <tbody>
                    {baris.map(([kelas, hadir, telat, alpa]) => (
                        <tr
                            key={kelas}
                            className="border-t border-[var(--garis)]"
                        >
                            <td className="py-1.5">{kelas}</td>
                            <td className="py-1.5 text-[var(--hijau)]">
                                {hadir}
                            </td>
                            <td className="py-1.5 text-[var(--kuning)]">
                                {telat}
                            </td>
                            <td className="py-1.5">{alpa}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function PemindaiGerbang() {
    return (
        <div className="flex w-full max-w-sm flex-col items-center rounded-[14px] border border-[var(--garis)] bg-[var(--kartu)] p-6">
            <AnimasiLottie nama="scan" className="size-40" />
            <p className="data mt-2 text-xs text-[var(--hijau)]">
                ABSENSI MASUK TERCATAT · 06.58
            </p>
        </div>
    );
}

const BAGIAN: Bagian[] = [
    {
        kode: 'Gerbang',
        judul: 'Absen di gerbang tanpa antre panjang',
        pengantar:
            'Pemindai cukup dibuka di browser. Tidak ada aplikasi yang perlu dipasang di perangkat gerbang.',
        poin: [
            'Kamera HP atau tablet, barcode reader USB, atau perangkat STB di gerbang.',
            'Jam masuk dan pulang mengikuti jadwal yang diatur tiap sekolah.',
            'Kartu yang dipindai dua kali tidak tercatat ganda.',
        ],
        visual: <PemindaiGerbang />,
    },
    {
        kode: 'Orang tua',
        judul: 'Kabar ke orang tua saat ada yang perlu diketahui',
        pengantar:
            'WhatsApp tidak dipenuhi pesan setiap pagi. Pesan hanya dikirim saat siswa terlambat atau tidak hadir.',
        poin: [
            'Riwayat kehadiran lengkap bisa dilihat di Portal Orang Tua.',
            'Orang tua juga bisa menghubungkan Telegram untuk menerima kabar.',
            'Satu akun orang tua untuk kakak dan adik di sekolah yang sama.',
        ],
        visual: <PesanWhatsapp />,
    },
    {
        kode: 'Laporan',
        judul: 'Rekap kehadiran siap dibagikan per kelas',
        pengantar:
            'Hadir, terlambat, izin, sakit, dan alpa setiap siswa terhitung dari catatan gerbang.',
        poin: [
            'Unduh Excel dengan satu sheet per kelas, atau PDF dengan satu halaman per kelas.',
            'Absensi sholat Dhuha dan Dzuhur bisa diaktifkan untuk sekolah yang memerlukannya.',
            'Pilih rentang tanggal dan kelas sebelum mengunduh.',
        ],
        visual: <RekapKelas />,
    },
];

export function Fitur() {
    return (
        <section
            id="fitur"
            className="scroll-mt-20 border-y border-[var(--garis)] bg-[var(--kartu)]/40"
        >
            {BAGIAN.map((bagian, i) => (
                <div
                    key={bagian.kode}
                    className={`mx-auto grid max-w-6xl items-center gap-10 px-4 py-20 sm:px-6 md:grid-cols-2 lg:gap-20 lg:py-24 ${
                        i > 0 ? 'border-t border-[var(--garis)]' : ''
                    }`}
                >
                    <div className={i % 2 === 1 ? 'md:order-2' : ''}>
                        <Muncul as="p" className="kode-tepi">
                            {bagian.kode}
                        </Muncul>
                        <Muncul
                            as="h2"
                            tunda={100}
                            className="tampil mt-4 text-[clamp(1.7rem,3vw,2.4rem)] leading-tight font-extrabold"
                        >
                            {bagian.judul}
                        </Muncul>
                        <Muncul
                            as="p"
                            tunda={200}
                            className="mt-4 max-w-md text-[var(--tinta-2)]"
                        >
                            {bagian.pengantar}
                        </Muncul>
                        <ul className="mt-6 space-y-3">
                            {bagian.poin.map((poin, j) => (
                                <Muncul
                                    as="li"
                                    key={poin}
                                    tunda={280 + j * 90}
                                    className="flex gap-3 text-sm leading-relaxed"
                                >
                                    <span
                                        className="mt-2 h-px w-4 shrink-0 bg-[var(--biru)]"
                                        aria-hidden="true"
                                    />
                                    {poin}
                                </Muncul>
                            ))}
                        </ul>
                    </div>
                    <Muncul
                        gaya="cetak"
                        tunda={200}
                        className="flex justify-center"
                    >
                        {bagian.visual}
                    </Muncul>
                </div>
            ))}
        </section>
    );
}
