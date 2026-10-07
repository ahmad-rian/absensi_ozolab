import {
    Camera,
    ClipboardPen,
    FileSpreadsheet,
    IdCard,
    MessageCircle,
    ScanLine,
} from 'lucide-react';
import { Muncul } from '@/components/depan/muncul';

/** Enam langkah yang dilalui satu siswa, berurutan. */
const LANGKAH = [
    { ikon: ClipboardPen, label: 'Daftar online' },
    { ikon: Camera, label: 'Foto di studio' },
    { ikon: IdCard, label: 'Kartu pelajar' },
    { ikon: ScanLine, label: 'Absen di gerbang' },
    { ikon: MessageCircle, label: 'Kabar orang tua' },
    { ikon: FileSpreadsheet, label: 'Laporan per kelas' },
];

export function Ragam() {
    return (
        <section
            id="alur"
            className="scroll-mt-24 px-5 py-24 text-center sm:py-32"
        >
            <Muncul
                as="h2"
                className="serif mx-auto max-w-2xl text-[clamp(2rem,4.2vw,3.1rem)] leading-[1.1]"
            >
                Tyas Photo bukan cuma soal foto, ini soal <em>hari sekolah</em>{' '}
                anak Anda.
            </Muncul>
            <ol className="mx-auto mt-14 grid max-w-4xl grid-cols-3 gap-y-10 sm:grid-cols-6">
                {LANGKAH.map(({ ikon: Ikon, label }, i) => (
                    <Muncul
                        as="li"
                        key={label}
                        tunda={i * 90}
                        className="flex flex-col items-center gap-3"
                    >
                        <Ikon
                            className="size-8"
                            strokeWidth={1.4}
                            aria-hidden="true"
                        />
                        <span className="text-sm font-semibold">{label}</span>
                    </Muncul>
                ))}
            </ol>
        </section>
    );
}
