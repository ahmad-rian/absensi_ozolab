import { Muncul } from '@/components/depan/muncul';

/** Urutan nyata yang dilalui satu siswa — karena itu bernomor. */
const LANGKAH = [
    {
        judul: 'Daftar online',
        isi: 'Orang tua mengisi formulir: sekolah dan kelas, data siswa, nomor WhatsApp, dan kata sandi Portal Orang Tua.',
    },
    {
        judul: 'Foto di studio',
        isi: 'Siswa difoto saat sesi foto sekolah. Fotonya langsung tersimpan di data siswa, tidak perlu dikirim ulang.',
    },
    {
        judul: 'Kartu dicetak',
        isi: 'Kartu pelajar dengan foto dan QR pribadi siswa, beserta pas foto yang siap cetak.',
    },
    {
        judul: 'Scan di gerbang',
        isi: 'Kartu dipindai kamera HP, tablet, atau barcode reader. Jam masuk dan pulang tercatat otomatis.',
    },
    {
        judul: 'Orang tua tahu',
        isi: 'Pesan WhatsApp terkirim saat siswa terlambat atau tidak hadir. Riwayat lengkap ada di Portal Orang Tua.',
    },
];

/** Lubang perforasi tepi film, atas dan bawah setiap bingkai. */
function Perforasi() {
    return (
        <div className="flex justify-between px-3" aria-hidden="true">
            {Array.from({ length: 7 }, (_, i) => (
                <span
                    key={i}
                    className="h-2 w-3 rounded-[2px] bg-[var(--kertas)]"
                />
            ))}
        </div>
    );
}

export function Alur() {
    return (
        <section id="alur" className="scroll-mt-20 pt-12 pb-20 lg:py-28">
            <div className="mx-auto max-w-6xl px-4 sm:px-6">
                <Muncul as="p" className="kode-tepi">
                    Alur satu siswa
                </Muncul>
                <Muncul
                    as="h2"
                    tunda={100}
                    className="tampil mt-4 max-w-2xl text-[clamp(1.8rem,3.6vw,2.8rem)] leading-tight font-extrabold"
                >
                    Dari formulir pendaftaran sampai kabar ke orang tua, tanpa
                    data diketik ulang.
                </Muncul>
                <p className="kode-tepi mt-4 md:hidden" aria-hidden="true">
                    Geser untuk melihat langkah berikutnya →
                </p>
            </div>

            <ol
                aria-label="Langkah"
                className="mx-auto mt-12 flex max-w-6xl snap-x snap-mandatory scroll-px-4 gap-3 overflow-x-auto px-4 pb-3 sm:px-6 md:grid md:grid-cols-5 md:overflow-visible md:pb-0"
            >
                {LANGKAH.map((langkah, i) => (
                    <Muncul
                        as="li"
                        key={langkah.judul}
                        tunda={i * 110}
                        className="flex w-[72vw] max-w-72 shrink-0 snap-start flex-col gap-2 rounded-md bg-[var(--tinta)] py-2 text-[var(--kertas)] md:w-auto md:max-w-none"
                    >
                        <Perforasi />
                        <div className="flex flex-1 flex-col gap-3 bg-[var(--kartu)] p-4 text-[var(--tinta)]">
                            <span className="data text-xs text-[var(--biru)]">
                                {String(i + 1).padStart(2, '0')}
                            </span>
                            <h3 className="tampil text-lg leading-snug font-bold">
                                {langkah.judul}
                            </h3>
                            <p className="text-sm leading-relaxed text-[var(--tinta-2)]">
                                {langkah.isi}
                            </p>
                        </div>
                        <Perforasi />
                    </Muncul>
                ))}
            </ol>
        </section>
    );
}
