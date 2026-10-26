import { Link } from '@inertiajs/react';
import { PILIHAN_STATUS } from '@/components/absensi-kelas/types';
import type { SiswaKelas } from '@/components/absensi-kelas/types';
import { Badge } from '@/components/ui/badge';

/** Tab "Siswa": anggota kelas beserta status masuk pada tanggal yang dibuka. */
export function DaftarSiswaKelas({ siswa }: { siswa: SiswaKelas[] }) {
    if (siswa.length === 0) {
        return (
            <p className="py-10 text-center text-sm text-muted-foreground">
                Kelas ini belum punya siswa aktif.
            </p>
        );
    }

    return (
        <ul className="divide-y divide-border rounded-2xl border bg-card">
            {siswa.map((s, i) => {
                const status = PILIHAN_STATUS.find((p) => p.nilai === s.status);

                return (
                    <li key={s.id} className="flex items-center gap-3 p-3">
                        <span className="w-6 text-right text-sm text-muted-foreground tabular-nums">
                            {i + 1}.
                        </span>
                        <div className="min-w-0 flex-1">
                            <Link
                                href={`/admin/siswa/${s.id}`}
                                className="truncate font-medium hover:underline"
                            >
                                {s.nama}
                            </Link>
                            <p className="text-xs text-muted-foreground tabular-nums">
                                NIS {s.nis ?? '-'} · NISN {s.nisn ?? '-'}
                            </p>
                        </div>
                        {status ? (
                            <Badge className={status.aktif}>
                                {status.label}
                            </Badge>
                        ) : (
                            <Badge variant="outline">Belum absen</Badge>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}
