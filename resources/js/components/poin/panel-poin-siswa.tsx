import { Plus } from 'lucide-react';
import { useState } from 'react';
import { CatatPelanggaran } from '@/components/poin/catat-pelanggaran';
import type {
    KategoriPoin,
    PilihanSiswa,
} from '@/components/poin/catat-pelanggaran';
import { KartuTotalPoin } from '@/components/poin/level-poin';
import type { AmbangLevel, LevelPoin } from '@/components/poin/level-poin';
import { TabelRiwayatPoin } from '@/components/poin/tabel-riwayat-poin';
import type { BarisPoin } from '@/components/poin/tabel-riwayat-poin';
import { Button } from '@/components/ui/button';

export type PoinSiswaPayload = {
    total: number;
    level: LevelPoin;
    semester_mulai: string;
    level_ambang: AmbangLevel;
    kategori: KategoriPoin[];
    riwayat: BarisPoin[];
};

/** Isi tab Poin Pelanggaran di detail siswa; guru BK mencatat langsung dari sini. */
export function PanelPoinSiswa({
    poin,
    siswa,
    bolehKelola,
}: {
    poin: PoinSiswaPayload;
    siswa: PilihanSiswa;
    bolehKelola: boolean;
}) {
    const [catat, setCatat] = useState(false);

    return (
        <div className="grid gap-4">
            {bolehKelola && (
                <div className="flex justify-end">
                    <Button onClick={() => setCatat(true)}>
                        <Plus className="size-4" /> Catat pelanggaran
                    </Button>
                </div>
            )}
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
                    bolehHapus={bolehKelola}
                    reloadOnly={['poin']}
                />
            </div>
            {bolehKelola && (
                <CatatPelanggaran
                    open={catat}
                    onOpenChange={setCatat}
                    kategori={poin.kategori}
                    siswaTetap={siswa}
                    reloadOnly={['poin']}
                />
            )}
        </div>
    );
}
