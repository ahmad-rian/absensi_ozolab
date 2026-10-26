import { router } from '@inertiajs/react';
import { CheckCheck, Save } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    show,
    store,
} from '@/actions/App/Http/Controllers/Admin/AbsensiKelasController';
import { PILIHAN_STATUS } from '@/components/absensi-kelas/types';
import type {
    KelasRingkas,
    SiswaKelas,
    Status,
} from '@/components/absensi-kelas/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type Pilihan = { status: Status | null; catatan: string };

function awal(siswa: SiswaKelas[]): Record<string, Pilihan> {
    return Object.fromEntries(
        siswa.map((s) => [
            s.id,
            { status: s.status, catatan: s.catatan ?? '' },
        ]),
    );
}

type FormAbsenKelasProps = {
    kelas: KelasRingkas;
    tanggal: string;
    siswa: SiswaKelas[];
    errors: Record<string, string>;
};

/**
 * Tab "Absen": guru memilih status masuk tiap siswa untuk satu tanggal.
 * Pasang dengan `key` kelas+tanggal supaya pilihan lama tidak terbawa.
 */
export function FormAbsenKelas({
    kelas,
    tanggal,
    siswa,
    errors,
}: FormAbsenKelasProps) {
    const [pilihan, setPilihan] = useState<Record<string, Pilihan>>(() =>
        awal(siswa),
    );
    const [menyimpan, setMenyimpan] = useState(false);

    const berubah = useMemo(
        () =>
            siswa.filter((s) => {
                const p = pilihan[s.id];

                return (
                    p?.status !== null &&
                    p !== undefined &&
                    (p.status !== s.status || p.catatan !== (s.catatan ?? ''))
                );
            }),
        [siswa, pilihan],
    );

    const ringkasan = useMemo(() => {
        const hitung: Record<string, number> = {};
        Object.values(pilihan).forEach((p) => {
            const kunci = p.status ?? 'KOSONG';
            hitung[kunci] = (hitung[kunci] ?? 0) + 1;
        });

        return hitung;
    }, [pilihan]);

    function pilih(id: string, status: Status) {
        setPilihan((lama) => ({ ...lama, [id]: { ...lama[id], status } }));
    }

    function ubahCatatan(id: string, catatan: string) {
        setPilihan((lama) => ({ ...lama, [id]: { ...lama[id], catatan } }));
    }

    function tandaiSisaHadir() {
        setPilihan((lama) =>
            Object.fromEntries(
                Object.entries(lama).map(([id, p]) => [
                    id,
                    p.status === null ? { ...p, status: 'HADIR' as Status } : p,
                ]),
            ),
        );
    }

    function pindahTanggal(tgl: string) {
        router.get(
            show.url(kelas.id, { query: { tanggal: tgl, tab: 'absen' } }),
        );
    }

    function simpan() {
        router.post(
            store.url(kelas.id),
            {
                tanggal,
                baris: berubah.map((s) => ({
                    student_id: s.id,
                    status: pilihan[s.id].status,
                    catatan: pilihan[s.id].catatan || null,
                })),
            },
            {
                preserveScroll: true,
                onStart: () => setMenyimpan(true),
                onFinish: () => setMenyimpan(false),
            },
        );
    }

    return (
        <div className="flex flex-col gap-4">
            {(errors.baris || errors.tanggal) && (
                <div className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-300">
                    {errors.baris ?? errors.tanggal}
                </div>
            )}

            <Card>
                <CardContent className="grid gap-4 pt-0 sm:grid-cols-2">
                    <div className="space-y-1.5">
                        <Label htmlFor="tanggal">Tanggal</Label>
                        <Input
                            id="tanggal"
                            type="date"
                            value={tanggal}
                            onChange={(e) =>
                                e.target.value && pindahTanggal(e.target.value)
                            }
                        />
                    </div>
                    <div className="flex items-end">
                        <Button
                            type="button"
                            variant="outline"
                            className="w-full"
                            onClick={tandaiSisaHadir}
                        >
                            <CheckCheck className="mr-2 size-4" /> Tandai
                            sisanya Hadir
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <div className="flex flex-wrap gap-2 text-sm">
                {PILIHAN_STATUS.map((s) => (
                    <Badge key={s.nilai} variant="outline">
                        {s.label}: {ringkasan[s.nilai] ?? 0}
                    </Badge>
                ))}
                <Badge variant="outline">
                    Belum diisi: {ringkasan.KOSONG ?? 0}
                </Badge>
            </div>

            {siswa.length === 0 ? (
                <p className="py-10 text-center text-sm text-muted-foreground">
                    Kelas ini belum punya siswa aktif.
                </p>
            ) : (
                <ul className="divide-y divide-border rounded-2xl border bg-card">
                    {siswa.map((s, i) => {
                        const p = pilihan[s.id];

                        return (
                            <li
                                key={s.id}
                                className="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:gap-4"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium">
                                        <span className="mr-2 text-muted-foreground tabular-nums">
                                            {i + 1}.
                                        </span>
                                        {s.nama}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {s.nis ?? '-'}
                                        {s.sumber && (
                                            <span className="ml-2">
                                                ·{' '}
                                                {s.sumber === 'scan'
                                                    ? `scan ${s.jam ?? ''}`
                                                    : 'diisi manual'}
                                            </span>
                                        )}
                                    </p>
                                </div>
                                <div
                                    className="flex gap-1.5"
                                    role="radiogroup"
                                    aria-label={`Status ${s.nama}`}
                                >
                                    {PILIHAN_STATUS.map((opsi) => (
                                        <button
                                            key={opsi.nilai}
                                            type="button"
                                            role="radio"
                                            aria-checked={
                                                p.status === opsi.nilai
                                            }
                                            title={opsi.label}
                                            onClick={() =>
                                                pilih(s.id, opsi.nilai)
                                            }
                                            className={cn(
                                                'size-9 rounded-lg border text-sm font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                                p.status === opsi.nilai
                                                    ? opsi.aktif
                                                    : 'hover:bg-muted',
                                            )}
                                        >
                                            {opsi.singkat}
                                        </button>
                                    ))}
                                </div>
                                <Input
                                    className="sm:w-48"
                                    placeholder="Catatan (opsional)"
                                    maxLength={500}
                                    value={p.catatan}
                                    onChange={(e) =>
                                        ubahCatatan(s.id, e.target.value)
                                    }
                                />
                            </li>
                        );
                    })}
                </ul>
            )}

            <div className="sticky bottom-0 z-20 -mx-4 border-t bg-background/95 p-3 backdrop-blur md:-mx-6">
                <div className="flex items-center justify-between gap-3">
                    <p className="text-sm text-muted-foreground">
                        {berubah.length} perubahan belum disimpan
                    </p>
                    <Button
                        type="button"
                        disabled={berubah.length === 0 || menyimpan}
                        onClick={simpan}
                    >
                        <Save className="mr-2 size-4" />
                        {menyimpan ? 'Menyimpan...' : 'Simpan'}
                    </Button>
                </div>
            </div>
        </div>
    );
}
