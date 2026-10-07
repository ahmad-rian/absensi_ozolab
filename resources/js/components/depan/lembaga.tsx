import { Muncul } from '@/components/depan/muncul';

const KEGUNAAN = [
    {
        judul: 'Kartu peserta',
        isi: 'Layout kartu sendiri, foto peserta, dan QR absensi yang tidak bisa dipalsukan.',
    },
    {
        judul: 'Absen setiap kegiatan',
        isi: 'Petugas memindai kartu dengan HP. Masuk dan pulang tercatat terpisah.',
    },
    {
        judul: 'Laporan per kelompok',
        isi: 'Unduh Excel atau PDF, dipisah per layout kartu.',
    },
];

export function Lembaga() {
    return (
        <section className="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:py-28">
            <div className="grid gap-10 lg:grid-cols-[0.9fr_1.1fr] lg:gap-20">
                <div>
                    <Muncul as="p" className="kode-tepi">
                        Di luar sekolah
                    </Muncul>
                    <Muncul
                        as="h2"
                        tunda={100}
                        className="tampil mt-4 text-[clamp(1.7rem,3vw,2.4rem)] leading-tight font-extrabold"
                    >
                        Kelompok bimbingan haji dan umrah memakai alur yang
                        sama.
                    </Muncul>
                    <Muncul
                        as="p"
                        tunda={200}
                        className="mt-4 text-[var(--tinta-2)]"
                    >
                        Peserta difoto, kartunya dicetak dengan QR, lalu
                        kehadiran di setiap manasik tercatat tanpa daftar hadir
                        kertas.
                    </Muncul>
                </div>
                <div className="grid gap-px overflow-hidden rounded-[14px] border border-[var(--garis)] bg-[var(--garis)] sm:grid-cols-3">
                    {KEGUNAAN.map((k, i) => (
                        <Muncul
                            key={k.judul}
                            tunda={i * 120}
                            className="bg-[var(--kartu)] p-6"
                        >
                            <h3 className="tampil text-lg font-bold">
                                {k.judul}
                            </h3>
                            <p className="mt-2 text-sm leading-relaxed text-[var(--tinta-2)]">
                                {k.isi}
                            </p>
                        </Muncul>
                    ))}
                </div>
            </div>
        </section>
    );
}
