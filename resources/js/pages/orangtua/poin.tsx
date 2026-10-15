import { KosongTanpaAnak, PortalPage } from '@/components/orangtua/portal-page';
import type { Anak } from '@/components/orangtua/portal-page';
import { GAYA_LEVEL, KartuTotalPoin } from '@/components/poin/level-poin';
import type { AmbangLevel, LevelPoin } from '@/components/poin/level-poin';
import { cn } from '@/lib/utils';

type Baris = {
    id: string;
    tanggal: string;
    sumber: 'TERLAMBAT' | 'ALPA' | 'MANUAL';
    label: string;
    poin: number;
    alasan: string | null;
};

type Props = {
    student: Anak | null;
    poin?: {
        total: number;
        level: LevelPoin;
        semester_mulai: string;
        level_ambang: AmbangLevel;
        riwayat: Baris[];
    };
};

const tanggal = (iso: string) =>
    new Intl.DateTimeFormat('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }).format(new Date(`${iso}T00:00:00`));

/** Poin pelanggaran anak (hanya baca). Sekolah yang mencatat. */
export default function PoinOrangTua({ student, poin }: Props) {
    if (!student || !poin) {
        return (
            <PortalPage title="Poin Pelanggaran" student={null}>
                <KosongTanpaAnak />
            </PortalPage>
        );
    }

    return (
        <PortalPage
            title="Poin Pelanggaran"
            description={`Catatan kedisiplinan ${student.name} semester ini. Terlambat dan tidak hadir tanpa keterangan tercatat otomatis.`}
            student={student}
        >
            <div className="grid gap-4 md:grid-cols-[20rem_1fr]">
                <div className="grid content-start gap-3">
                    <KartuTotalPoin
                        total={poin.total}
                        level={poin.level}
                        ambang={poin.level_ambang}
                        semesterMulai={poin.semester_mulai}
                    />
                    <div className="grid gap-1.5 rounded-xl border bg-card p-4 text-sm">
                        {(['kuning', 'orange', 'merah'] as const).map((l) => (
                            <div
                                key={l}
                                className="flex items-center justify-between"
                            >
                                <span className="flex items-center gap-2">
                                    <span
                                        className={cn(
                                            'size-2 rounded-full',
                                            GAYA_LEVEL[l].titik,
                                        )}
                                    />{' '}
                                    {GAYA_LEVEL[l].label}
                                </span>
                                <span className="text-muted-foreground tabular-nums">
                                    mulai {poin.level_ambang[l]} poin
                                </span>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="rounded-xl border bg-card">
                    {poin.riwayat.length === 0 ? (
                        <p className="p-6 text-center text-sm text-muted-foreground">
                            Belum ada catatan. Pertahankan!
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {poin.riwayat.map((b) => (
                                <li
                                    key={b.id}
                                    className="flex items-start justify-between gap-3 p-4"
                                >
                                    <div>
                                        <p className="font-medium">{b.label}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {tanggal(b.tanggal)}
                                        </p>
                                        {b.alasan && (
                                            <p className="mt-1 text-sm whitespace-pre-line">
                                                {b.alasan}
                                            </p>
                                        )}
                                    </div>
                                    <span className="shrink-0 font-semibold tabular-nums">
                                        +{b.poin}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </PortalPage>
    );
}
