import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { AnimasiLottie } from '@/components/depan/animasi-lottie';
import { Muncul } from '@/components/depan/muncul';

/** Contoh catatan gerbang di kartu hero. Nama fiktif, jam realistis. */
const LOG = [
    { jam: '06.41', nama: 'Alya R.', kelas: 'VIII B', status: 'Hadir' },
    { jam: '06.44', nama: 'Bima S.', kelas: 'VII A', status: 'Hadir' },
    { jam: '06.52', nama: 'Citra N.', kelas: 'IX C', status: 'Hadir' },
    { jam: '06.58', nama: 'Dimas P.', kelas: 'VIII A', status: 'Hadir' },
    { jam: '07.16', nama: 'Eka W.', kelas: 'VII B', status: 'Terlambat' },
    { jam: '07.02', nama: 'Fajar H.', kelas: 'IX A', status: 'Hadir' },
] as const;

/** Pola QR hiasan yang tetap (tidak acak tiap render, tidak bisa dipindai). */
const POLA = Array.from({ length: 21 * 21 }, (_, i) => {
    const x = i % 21;
    const y = Math.floor(i / 21);
    const penanda = (a: number, b: number) =>
        a >= 0 &&
        a < 7 &&
        b >= 0 &&
        b < 7 &&
        (a % 6 === 0 || b % 6 === 0 || (a > 1 && a < 5 && b > 1 && b < 5));

    if (penanda(x, y) || penanda(x - 14, y) || penanda(x, y - 14)) {
        return true;
    }

    if ((x < 8 && y < 8) || (x > 12 && y < 8) || (x < 8 && y > 12)) {
        return false;
    }

    return (x * 7 + y * 13 + x * y) % 5 < 2;
});

function KartuPelajar() {
    return (
        <div className="tanda-potong w-[min(19rem,78vw)] rounded-[14px] border border-[var(--garis)] bg-[var(--kartu)] p-4 shadow-[0_30px_60px_-30px_rgba(17,22,27,0.35)]">
            <div className="flex items-center justify-between rounded-lg bg-[var(--biru)] px-3 py-2 text-white">
                <span className="data text-[0.68rem] font-medium">
                    KARTU PELAJAR
                </span>
                <span className="data text-[0.68rem] opacity-80">
                    2026/2027
                </span>
            </div>
            <div className="mt-4 flex gap-4">
                <div className="flex aspect-[3/4] w-24 items-end justify-center overflow-hidden rounded-md bg-[var(--biru-muda)]">
                    <svg
                        viewBox="0 0 60 80"
                        className="w-full text-[var(--biru)]"
                        aria-hidden="true"
                    >
                        <circle
                            cx="30"
                            cy="30"
                            r="13"
                            fill="currentColor"
                            opacity="0.55"
                        />
                        <path
                            d="M6 80c2-18 12-27 24-27s22 9 24 27z"
                            fill="currentColor"
                            opacity="0.55"
                        />
                    </svg>
                </div>
                <div className="min-w-0 flex-1 space-y-2 text-sm">
                    <p className="tampil text-base leading-tight font-bold">
                        Alya Rahmawati
                    </p>
                    <p className="data text-xs text-[var(--tinta-2)]">
                        NIS 0072318
                    </p>
                    <p className="data text-xs text-[var(--tinta-2)]">
                        KELAS VIII B
                    </p>
                </div>
            </div>
            <div className="relative mt-4 flex items-center gap-4">
                <svg
                    viewBox="0 0 21 21"
                    className="size-24 shrink-0 rounded bg-white p-1.5"
                    shapeRendering="crispEdges"
                    aria-hidden="true"
                >
                    {POLA.map((isi, i) =>
                        isi ? (
                            <rect
                                key={i}
                                x={i % 21}
                                y={Math.floor(i / 21)}
                                width="1"
                                height="1"
                                fill="#11161b"
                            />
                        ) : null,
                    )}
                </svg>
                <AnimasiLottie
                    nama="scan"
                    className="pointer-events-none absolute -top-3 -left-3 size-30"
                />
                <p className="text-xs leading-relaxed text-[var(--tinta-2)]">
                    QR pribadi siswa. Tempelkan ke pemindai di gerbang.
                </p>
            </div>
        </div>
    );
}

function LogGerbang() {
    return (
        <div className="w-[min(17rem,78vw)] overflow-hidden rounded-[14px] border border-[var(--garis)] bg-[var(--kartu)]">
            <div className="flex items-center justify-between border-b border-[var(--garis)] px-4 py-3">
                <span className="kode-tepi">Gerbang utama</span>
                <span className="data flex items-center gap-1.5 text-[0.68rem] text-[var(--hijau)]">
                    <span className="titik-langsung size-1.5 rounded-full bg-current" />
                    LANGSUNG
                </span>
            </div>
            <div className="h-48 overflow-hidden">
                <ul className="gulir-log">
                    {[...LOG, ...LOG].map((baris, i) => (
                        <li
                            key={i}
                            className="flex items-center gap-3 border-b border-[var(--garis)] px-4 py-2.5 text-sm last:border-0"
                        >
                            <span className="data text-xs text-[var(--tinta-3)]">
                                {baris.jam}
                            </span>
                            <span className="min-w-0 flex-1 truncate">
                                {baris.nama}{' '}
                                <span className="text-[var(--tinta-3)]">
                                    · {baris.kelas}
                                </span>
                            </span>
                            <span
                                className={`data rounded px-1.5 py-0.5 text-[0.65rem] ${
                                    baris.status === 'Hadir'
                                        ? 'bg-[var(--hijau-muda)] text-[var(--hijau)]'
                                        : 'bg-[var(--kuning-muda)] text-[var(--kuning)]'
                                }`}
                            >
                                {baris.status.toUpperCase()}
                            </span>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}

export function Hero() {
    return (
        <section className="mx-auto grid max-w-6xl items-center gap-14 px-4 pt-28 pb-12 sm:px-6 lg:grid-cols-[1.1fr_0.9fr] lg:pt-36 lg:pb-28">
            <div>
                <Muncul as="p" className="kode-tepi" tunda={0}>
                    Tyas Photo · Studio foto &amp; absensi sekolah
                </Muncul>
                <Muncul
                    as="h1"
                    tunda={120}
                    className="tampil mt-5 text-[clamp(2.3rem,5.4vw,4.1rem)] leading-[1.02] font-extrabold"
                >
                    Difoto di studio, dicetak jadi kartu,{' '}
                    <span className="goresan">dipakai absen</span> setiap pagi.
                </Muncul>
                <Muncul
                    as="p"
                    tunda={260}
                    className="mt-6 max-w-[34rem] text-lg leading-relaxed text-[var(--tinta-2)]"
                >
                    Orang tua mendaftarkan siswa secara online, siswa difoto di
                    studio Tyas Photo, lalu kartu ber-QR dipindai di gerbang.
                    Orang tua mendapat kabar saat anaknya terlambat atau tidak
                    hadir.
                </Muncul>
                <Muncul tunda={380} className="mt-9 flex flex-wrap gap-3">
                    <Link
                        href="/daftar"
                        className="group inline-flex h-12 items-center gap-2 rounded-lg bg-[var(--tinta)] px-6 font-semibold text-[var(--kertas)] transition hover:bg-[var(--biru-tua)]"
                    >
                        Daftarkan Siswa
                        <ArrowRight className="size-4 transition group-hover:translate-x-0.5" />
                    </Link>
                    <Link
                        href="/login"
                        className="inline-flex h-12 items-center rounded-lg border border-[var(--garis)] bg-[var(--kartu)] px-6 font-semibold transition hover:border-[var(--tinta-3)]"
                    >
                        Masuk
                    </Link>
                </Muncul>
            </div>

            <div className="relative flex flex-col items-center gap-6 sm:flex-row sm:items-end sm:justify-center lg:block lg:h-[33rem]">
                <Muncul
                    gaya="cetak"
                    tunda={300}
                    className="lg:absolute lg:top-0 lg:left-0"
                >
                    <KartuPelajar />
                </Muncul>
                <Muncul
                    tunda={700}
                    className="lg:absolute lg:top-[19.5rem] lg:right-0"
                >
                    <LogGerbang />
                </Muncul>
            </div>
        </section>
    );
}
