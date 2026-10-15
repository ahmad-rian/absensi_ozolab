import { KartuTotalPoin } from '@/components/poin/level-poin';
import type { AmbangLevel, LevelPoin } from '@/components/poin/level-poin';
import { TabelRiwayatPoin } from '@/components/poin/tabel-riwayat-poin';
import type { BarisPoin } from '@/components/poin/tabel-riwayat-poin';

export type PoinSiswaPayload = {
    total: number;
    level: LevelPoin;
    semester_mulai: string;
    level_ambang: AmbangLevel;
    riwayat: BarisPoin[];
};

/** Isi tab Poin di detail siswa. */
export function PanelPoinSiswa({
    poin,
    bolehHapus,
}: {
    poin: PoinSiswaPayload;
    bolehHapus: boolean;
}) {
    return (
        <div className="grid gap-4 lg:grid-cols-[18rem_1fr]">
            <KartuTotalPoin
                total={poin.total}
                level={poin.level}
                ambang={poin.level_ambang}
                semesterMulai={poin.semester_mulai}
            />
            <TabelRiwayatPoin
                baris={poin.riwayat}
                tampilSiswa={false}
                bolehHapus={bolehHapus}
            />
        </div>
    );
}
